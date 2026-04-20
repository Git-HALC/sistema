<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use PDO;
use RuntimeException;

/**
 * Emissor de NFS-e via padrao nacional (Emissor Nacional gov.br).
 * Ancorado em pdv_vendas + pdv_venda_itens (tipo_item='SERVICO' ou 'servico').
 *
 * Fluxo:
 *   1. valida venda (cliente com CPF/CNPJ, servicos com fiscal_servicos_config)
 *   2. monta DPS (Documento Provisorio de Servicos) padrao ABRASF/Nacional
 *   3. autentica (ou reusa token) via NfseNacionalAutenticador
 *   4. transmite POST JSON para /dps/emitir
 *   5. persiste fiscal_nfse com status e numero_nfse
 */
final class NfseNacionalEmissor
{
    private PerfilTributarioRepository $perfilRepo;
    private NfseNacionalAutenticador $auth;

    public function __construct(private readonly PDO $pdo)
    {
        $this->perfilRepo = new PerfilTributarioRepository($pdo);
        $this->auth = new NfseNacionalAutenticador($pdo);
    }

    public function emitir(int $vendaId): array
    {
        $venda = $this->buscarVendaComServicos($vendaId);
        if (!$venda) {
            throw new RuntimeException('Venda nao encontrada.');
        }
        if (empty($venda['itens_servico'])) {
            throw new RuntimeException('Venda nao possui servicos.');
        }
        $this->validarParaEmissao($venda);

        $perfil = $this->perfilRepo->obterPerfil(1);
        if (!$perfil) {
            throw new RuntimeException('Perfil tributario nao configurado.');
        }
        $empresa = $this->pdo->query('SELECT * FROM empresa_local WHERE id=1')
            ->fetch(PDO::FETCH_ASSOC);

        $numeroRps = $this->perfilRepo->proximoNumero((int)$perfil['id'], 'rps');

        $dps = $this->montarDps($venda, $empresa, $perfil, $numeroRps);

        $this->pdo->beginTransaction();
        try {
            // Grava pendente primeiro (garante numero_rps registrado)
            $nfseId = $this->gravarPendente($vendaId, $numeroRps, $dps, $perfil);

            $token = $this->auth->tokenValidoOuRenovar();
            $resp = $this->transmitir($dps, $token, $perfil);

            $this->atualizarPosTransmissao($nfseId, $resp);
            $this->pdo->commit();
            return [
                'ok' => true,
                'id' => $nfseId,
                'numero_nfse' => $resp['numeroNfse'] ?? null,
                'status' => $resp['status'] ?? 'pendente',
                'codigo_verificacao' => $resp['codigoVerificacao'] ?? null,
                'link_nfse' => $resp['linkNfse'] ?? null,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new RuntimeException('Falha ao emitir NFS-e: ' . $e->getMessage(), 0, $e);
        }
    }

    private function buscarVendaComServicos(int $vendaId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.id, v.numero, v.valor_total, v.created_at, v.cliente_id,
                   c.nome AS cliente_nome, c.cpf_cnpj AS cliente_cpf_cnpj,
                   c.email AS cliente_email, c.telefone AS cliente_telefone,
                   c.logradouro, c.numero_endereco, c.complemento, c.bairro,
                   c.cidade, c.estado AS uf, c.cep, c.codigo_municipio,
                   c.ie AS cliente_ie, c.ind_ie_dest
              FROM pdv_vendas v
              LEFT JOIN clientes c ON c.id = v.cliente_id
             WHERE v.id = :id
        ");
        $stmt->execute([':id' => $vendaId]);
        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$venda) return null;

        $stmt2 = $this->pdo->prepare("
            SELECT i.*, s.nome AS servico_nome,
                   fsc.codigo_lc116, fsc.descricao_lc116, fsc.cnae,
                   fsc.codigo_municipio AS codigo_municipio_servico,
                   fsc.iss_aliquota, fsc.iss_retido,
                   fsc.pis_aliquota, fsc.cofins_aliquota,
                   fsc.csll_aliquota, fsc.ir_aliquota, fsc.inss_aliquota
              FROM pdv_venda_itens i
              LEFT JOIN servicos_catalogo s ON s.id = i.servico_id
              LEFT JOIN fiscal_servicos_config fsc ON fsc.servico_id = i.servico_id AND fsc.ativo = TRUE
             WHERE i.venda_id = :id
               AND UPPER(i.tipo_item) = 'SERVICO'
        ");
        $stmt2->execute([':id' => $vendaId]);
        $venda['itens_servico'] = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $venda;
    }

    private function validarParaEmissao(array $venda): void
    {
        $erros = [];
        if (empty($venda['cliente_cpf_cnpj'])) {
            $erros[] = 'Cliente sem CPF/CNPJ cadastrado.';
        }
        foreach ($venda['itens_servico'] as $it) {
            if (empty($it['codigo_lc116'])) {
                $erros[] = 'Serviço "' . $it['servico_nome'] . '" sem código LC 116 configurado.';
            }
        }
        if ($erros) {
            throw new RuntimeException(implode(' | ', $erros));
        }
    }

    /** Monta DPS no formato padrao nacional (ABRASF nacional). */
    private function montarDps(array $venda, array $empresa, array $perfil, int $numeroRps): array
    {
        $valorServicos = 0.0;
        $valorIss = 0.0;
        $aliqIssPrincipal = 0.0;
        $servicosDps = [];

        foreach ($venda['itens_servico'] as $i => $it) {
            $total = (float)$it['valor_total_item'];
            $aliq = (float)($it['iss_aliquota'] ?? 0);
            $valorIssItem = round($total * ($aliq / 100), 2);
            $valorServicos += $total;
            $valorIss += $valorIssItem;
            if ($i === 0) $aliqIssPrincipal = $aliq;

            $servicosDps[] = [
                'codigoLc116'      => (string)$it['codigo_lc116'],
                'cnae'             => (string)($it['cnae'] ?? ''),
                'codigoMunicipio'  => (string)($it['codigo_municipio_servico'] ?? $empresa['codigo_municipio'] ?? ''),
                'descricao'        => (string)$it['nome_item'],
                'quantidade'       => (float)$it['quantidade'],
                'valorUnitario'    => (float)$it['valor_unitario'],
                'valorTotal'       => $total,
                'aliquotaIss'      => $aliq,
                'valorIss'         => $valorIssItem,
                'issRetido'        => (bool)($it['iss_retido'] ?? false),
            ];
        }

        return [
            'versao'        => '1.00',
            'serieRps'      => (string)($perfil['serie_rps'] ?? 'RPS'),
            'numeroRps'     => $numeroRps,
            'dataEmissao'   => date('c'),
            'ambiente'      => (string)($perfil['ambiente_nfse'] ?? 'homologacao'),
            'prestador'     => [
                'cnpj'              => (string)($empresa['cnpj'] ?? ''),
                'inscricaoMunicipal'=> (string)($empresa['inscricao_municipal'] ?? ''),
                'razaoSocial'       => (string)($empresa['nome'] ?? ''),
                'codigoMunicipio'   => (string)($empresa['codigo_municipio'] ?? ''),
                'uf'                => strtoupper((string)($empresa['uf'] ?? '')),
            ],
            'tomador'       => [
                'cpfCnpj'          => preg_replace('/\D/', '', (string)($venda['cliente_cpf_cnpj'] ?? '')),
                'razaoSocial'      => (string)($venda['cliente_nome'] ?? 'Consumidor Final'),
                'email'            => (string)($venda['cliente_email'] ?? ''),
                'inscricaoEstadual'=> (string)($venda['cliente_ie'] ?? ''),
                'endereco' => [
                    'logradouro'      => (string)($venda['logradouro'] ?? ''),
                    'numero'          => (string)($venda['numero_endereco'] ?? ''),
                    'complemento'     => (string)($venda['complemento'] ?? ''),
                    'bairro'          => (string)($venda['bairro'] ?? ''),
                    'codigoMunicipio' => (string)($venda['codigo_municipio'] ?? ''),
                    'uf'              => strtoupper((string)($venda['uf'] ?? '')),
                    'cep'             => (string)($venda['cep'] ?? ''),
                ],
            ],
            'servicos' => $servicosDps,
            'totais' => [
                'valorServicos' => round($valorServicos, 2),
                'valorIss'      => round($valorIss, 2),
                'aliquotaIss'   => $aliqIssPrincipal,
            ],
        ];
    }

    private function gravarPendente(int $vendaId, int $numeroRps, array $dps, array $perfil): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO fiscal_nfse
                (venda_id, numero_rps, serie_rps, status, ambiente,
                 valor_servicos, valor_iss, aliquota_iss,
                 codigo_servico, discriminacao, xml_rps_enviado)
             VALUES
                (:v, :n, :s, 'pendente', :a,
                 :vs, :vi, :ai,
                 :cs, :d, :xe)
             RETURNING id"
        );
        $stmt->execute([
            ':v' => $vendaId,
            ':n' => $numeroRps,
            ':s' => (string)($perfil['serie_rps'] ?? 'RPS'),
            ':a' => (string)($perfil['ambiente_nfse'] ?? 'homologacao'),
            ':vs' => (float)$dps['totais']['valorServicos'],
            ':vi' => (float)$dps['totais']['valorIss'],
            ':ai' => (float)$dps['totais']['aliquotaIss'],
            ':cs' => (string)($dps['servicos'][0]['codigoLc116'] ?? ''),
            ':d' => implode('; ', array_column($dps['servicos'], 'descricao')),
            ':xe' => json_encode($dps, JSON_UNESCAPED_UNICODE),
        ]);
        return (int)$stmt->fetchColumn();
    }

    private function transmitir(array $dps, string $token, array $perfil): array
    {
        $base = (($perfil['ambiente_nfse'] ?? 'homologacao') === 'producao')
            ? 'https://adn.nfse.gov.br'
            : 'https://adn.nfse.gov.br/homologacao';
        $url = $base . '/dps/emitir';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dps, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Falha na transmissao da NFS-e.');
        }
        $resp = json_decode((string)$body, true) ?: ['raw' => (string)$body];
        if ($status >= 400) {
            $resp['_http_status'] = $status;
            $resp['_erro'] = $resp['mensagem'] ?? $resp['error'] ?? 'Erro HTTP ' . $status;
        } else {
            $resp['status'] = $resp['status'] ?? 'autorizada';
        }
        return $resp;
    }

    private function atualizarPosTransmissao(int $nfseId, array $resp): void
    {
        $statusFinal = $resp['status'] ?? 'pendente';
        if ($statusFinal === 'autorizada' || !empty($resp['numeroNfse'])) {
            $statusFinal = 'autorizada';
        } elseif (!empty($resp['_erro']) || ($resp['_http_status'] ?? 200) >= 400) {
            $statusFinal = 'rejeitada';
        }

        $stmt = $this->pdo->prepare(
            "UPDATE fiscal_nfse
                SET numero_nfse = :nn,
                    codigo_verificacao = :cv,
                    xml_nfse_retorno = :xr,
                    status = :st,
                    data_autorizacao = CASE WHEN :st2 = 'autorizada' THEN NOW() ELSE NULL END,
                    motivo_rejeicao = :mr,
                    link_nfse = :ln
              WHERE id = :id"
        );
        $stmt->execute([
            ':nn' => $resp['numeroNfse'] ?? null,
            ':cv' => $resp['codigoVerificacao'] ?? null,
            ':xr' => json_encode($resp, JSON_UNESCAPED_UNICODE),
            ':st' => $statusFinal,
            ':st2' => $statusFinal,
            ':mr' => $resp['_erro'] ?? ($resp['mensagem'] ?? null),
            ':ln' => $resp['linkNfse'] ?? null,
            ':id' => $nfseId,
        ]);
    }

    public function listarNfseVenda(int $vendaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM fiscal_nfse WHERE venda_id = :v ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':v' => $vendaId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
}
