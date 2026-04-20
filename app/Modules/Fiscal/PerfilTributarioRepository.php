<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use PDO;

/**
 * Acesso a dados de perfil_tributario, tributacao_por_estado e fiscal_servicos_config.
 * Singleton por empresa_local_id (default=1).
 */
final class PerfilTributarioRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function obterPerfil(int $empresaLocalId = 1): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM perfil_tributario WHERE empresa_local_id = :e LIMIT 1'
        );
        $stmt->execute([':e' => $empresaLocalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function criarPerfilSeNaoExiste(int $empresaLocalId = 1): int
    {
        $perfil = $this->obterPerfil($empresaLocalId);
        if ($perfil) {
            return (int)$perfil['id'];
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO perfil_tributario (empresa_local_id, regime_tributario)
             VALUES (:e, :r) RETURNING id'
        );
        $stmt->execute([':e' => $empresaLocalId, ':r' => 'simples_nacional']);
        return (int)$stmt->fetchColumn();
    }

    public function atualizarPerfil(int $id, array $dados): void
    {
        $permitidos = [
            'regime_tributario',
            'csc_id_homologacao', 'csc_token_homologacao',
            'csc_id_producao', 'csc_token_producao',
            'serie_nfce', 'numero_nfce_atual', 'ambiente_nfce',
            'nfse_modo_auth', 'nfse_usuario', 'nfse_senha_cifrada',
            'nfse_token', 'nfse_token_expira_em',
            'nfse_certificado_path', 'nfse_certificado_senha_cifrada',
            'serie_rps', 'numero_rps_atual', 'ambiente_nfse',
            'ativo',
        ];
        $sets = [];
        $params = [':id' => $id];
        foreach ($permitidos as $col) {
            if (array_key_exists($col, $dados)) {
                $sets[] = "$col = :$col";
                $params[":$col"] = $dados[$col];
            }
        }
        if (!$sets) return;
        $sql = 'UPDATE perfil_tributario SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function atualizarTokenNfse(int $perfilId, ?string $token, ?string $expiraEm): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE perfil_tributario SET nfse_token = :t, nfse_token_expira_em = :e
             WHERE id = :id'
        );
        $stmt->execute([':t' => $token, ':e' => $expiraEm, ':id' => $perfilId]);
    }

    /** @return array<int, array<string,mixed>> */
    public function listarTributacaoPorEstado(int $perfilId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tributacao_por_estado
              WHERE perfil_id = :p AND ativo = TRUE
              ORDER BY uf_destino"
        );
        $stmt->execute([':p' => $perfilId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Salva em lote todas as alíquotas por estado (UPSERT por uf_destino).
     * @param array<int, array<string,mixed>> $linhas
     */
    public function salvarTributacaoLote(int $perfilId, string $ufOrigem, array $linhas): int
    {
        $sql = "INSERT INTO tributacao_por_estado
                    (perfil_id, uf_origem, uf_destino,
                     icms_aliquota, icms_aliquota_inter, fcp_aliquota,
                     simples_anexo, simples_aliquota)
                VALUES (:p, :oo, :dd, :ia, :iai, :fcp, :sa, :saliq)
                ON CONFLICT (perfil_id, uf_origem, uf_destino)
                DO UPDATE SET
                    icms_aliquota = EXCLUDED.icms_aliquota,
                    icms_aliquota_inter = EXCLUDED.icms_aliquota_inter,
                    fcp_aliquota = EXCLUDED.fcp_aliquota,
                    simples_anexo = EXCLUDED.simples_anexo,
                    simples_aliquota = EXCLUDED.simples_aliquota,
                    updated_at = NOW()";
        $stmt = $this->pdo->prepare($sql);
        $n = 0;
        foreach ($linhas as $l) {
            $stmt->execute([
                ':p' => $perfilId,
                ':oo' => strtoupper((string)$ufOrigem),
                ':dd' => strtoupper((string)$l['uf_destino']),
                ':ia' => (float)($l['icms_aliquota'] ?? 0),
                ':iai' => (float)($l['icms_aliquota_inter'] ?? 0),
                ':fcp' => (float)($l['fcp_aliquota'] ?? 0),
                ':sa' => !empty($l['simples_anexo']) ? (int)$l['simples_anexo'] : null,
                ':saliq' => (float)($l['simples_aliquota'] ?? 0),
            ]);
            $n++;
        }
        return $n;
    }

    public function obterTributacaoPorUf(int $perfilId, string $ufOrigem, string $ufDestino): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tributacao_por_estado
              WHERE perfil_id = :p AND uf_origem = :o AND uf_destino = :d
              LIMIT 1"
        );
        $stmt->execute([
            ':p' => $perfilId,
            ':o' => strtoupper($ufOrigem),
            ':d' => strtoupper($ufDestino),
        ]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Servicos do catalogo com sua config fiscal (LEFT JOIN). */
    public function listarServicosComConfig(): array
    {
        $stmt = $this->pdo->query(
            "SELECT s.id, s.nome, s.ativo,
                    fsc.id AS config_id,
                    fsc.codigo_lc116, fsc.descricao_lc116, fsc.cnae,
                    fsc.codigo_municipio, fsc.iss_aliquota, fsc.iss_retido,
                    fsc.pis_aliquota, fsc.cofins_aliquota,
                    fsc.csll_aliquota, fsc.ir_aliquota, fsc.inss_aliquota
               FROM servicos_catalogo s
               LEFT JOIN fiscal_servicos_config fsc ON fsc.servico_id = s.id AND fsc.ativo = TRUE
              WHERE s.ativo = TRUE
              ORDER BY s.nome"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function salvarConfigServico(int $servicoId, array $dados): void
    {
        $sql = "INSERT INTO fiscal_servicos_config
                    (servico_id, codigo_lc116, descricao_lc116, cnae, codigo_municipio,
                     iss_aliquota, iss_retido, pis_aliquota, cofins_aliquota,
                     csll_aliquota, ir_aliquota, inss_aliquota)
                VALUES (:sid, :lc, :dlc, :cn, :cm, :iss, :ret, :pis, :cof, :csll, :ir, :inss)
                ON CONFLICT (servico_id) DO UPDATE SET
                    codigo_lc116 = EXCLUDED.codigo_lc116,
                    descricao_lc116 = EXCLUDED.descricao_lc116,
                    cnae = EXCLUDED.cnae,
                    codigo_municipio = EXCLUDED.codigo_municipio,
                    iss_aliquota = EXCLUDED.iss_aliquota,
                    iss_retido = EXCLUDED.iss_retido,
                    pis_aliquota = EXCLUDED.pis_aliquota,
                    cofins_aliquota = EXCLUDED.cofins_aliquota,
                    csll_aliquota = EXCLUDED.csll_aliquota,
                    ir_aliquota = EXCLUDED.ir_aliquota,
                    inss_aliquota = EXCLUDED.inss_aliquota,
                    updated_at = NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sid' => $servicoId,
            ':lc' => (string)($dados['codigo_lc116'] ?? ''),
            ':dlc' => (string)($dados['descricao_lc116'] ?? ''),
            ':cn' => (string)($dados['cnae'] ?? ''),
            ':cm' => (string)($dados['codigo_municipio'] ?? ''),
            ':iss' => (float)($dados['iss_aliquota'] ?? 0),
            ':ret' => !empty($dados['iss_retido']),
            ':pis' => (float)($dados['pis_aliquota'] ?? 0),
            ':cof' => (float)($dados['cofins_aliquota'] ?? 0),
            ':csll' => (float)($dados['csll_aliquota'] ?? 0),
            ':ir' => (float)($dados['ir_aliquota'] ?? 0),
            ':inss' => (float)($dados['inss_aliquota'] ?? 0),
        ]);
    }

    /**
     * Incrementa o numero atomicamente (row-level lock).
     * @param 'nfce'|'rps' $tipo
     */
    public function proximoNumero(int $perfilId, string $tipo): int
    {
        $col = $tipo === 'nfce' ? 'numero_nfce_atual' : 'numero_rps_atual';
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT $col FROM perfil_tributario WHERE id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $perfilId]);
            $atual = (int)$stmt->fetchColumn();
            $proximo = $atual + 1;
            $upd = $this->pdo->prepare(
                "UPDATE perfil_tributario SET $col = :n WHERE id = :id"
            );
            $upd->execute([':n' => $proximo, ':id' => $perfilId]);
            $this->pdo->commit();
            return $proximo;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
