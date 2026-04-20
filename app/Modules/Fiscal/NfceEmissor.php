<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use NFePHP\Common\Certificate;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use PDO;
use RuntimeException;

/**
 * Emissor de NFC-e via sped-nfe, ancorado em pdv_vendas + pdv_venda_itens (tipo PRODUTO).
 * Busca aliquotas em produto_fiscal + tributacao_por_estado por UF do cliente.
 */
final class NfceEmissor
{
    private PerfilTributarioRepository $perfilRepo;

    public function __construct(private readonly PDO $pdo)
    {
        $this->perfilRepo = new PerfilTributarioRepository($pdo);
    }

    public function emitir(int $vendaId): array
    {
        $venda = $this->buscarVendaComProdutos($vendaId);
        if (!$venda) {
            throw new RuntimeException('Venda nao encontrada.');
        }
        if (empty($venda['itens_produto'])) {
            throw new RuntimeException('Venda nao possui produtos.');
        }

        $perfil = $this->perfilRepo->obterPerfil(1);
        $empresa = $this->pdo->query('SELECT * FROM empresa_local WHERE id = 1')
            ->fetch(PDO::FETCH_ASSOC);
        if (!$perfil || !$empresa) {
            throw new RuntimeException('Perfil/empresa nao configurados.');
        }
        $this->validar($venda, $empresa, $perfil);

        $numero = $this->perfilRepo->proximoNumero((int)$perfil['id'], 'nfce');

        $make = new Make();
        $this->montarIde($make, $venda, $empresa, $perfil, $numero);
        $this->montarEmit($make, $empresa);
        $this->montarDest($make, $venda);
        $ufDestino = strtoupper((string)($venda['uf'] ?? $empresa['uf'] ?? 'SP'));
        $ufOrigem = strtoupper((string)($empresa['uf'] ?? 'SP'));
        $tributacao = $this->perfilRepo->obterTributacaoPorUf((int)$perfil['id'], $ufOrigem, $ufDestino) ?? [];

        foreach ($venda['itens_produto'] as $n => $it) {
            $this->montarItem($make, $n + 1, $it, $tributacao);
        }
        $this->montarTotal($make, $venda);
        $this->montarTransp($make);
        $this->montarPag($make, $venda);

        $xml = $make->getXML();
        if (!$xml) {
            throw new RuntimeException('Falha ao montar XML: ' . implode('; ', $make->getErrors()));
        }

        $this->pdo->beginTransaction();
        try {
            $nfceId = $this->gravarPendente($vendaId, $numero, $perfil, $xml, (float)$venda['valor_total']);

            $certPath = (string)($empresa['certificado_path'] ?? '');
            $certSenha = (string)($empresa['certificado_senha'] ?? '');
            if ($certPath === '' || !file_exists($certPath)) {
                throw new RuntimeException('Certificado A1 nao configurado.');
            }
            $cert = Certificate::readPfx(file_get_contents($certPath), $certSenha);

            $configJson = json_encode([
                'atualizacao' => date('Y-m-d H:i:s'),
                'tpAmb' => (int)$perfil['ambiente_nfce'],
                'razaosocial' => (string)$empresa['nome'],
                'siglaUF' => $ufOrigem,
                'cnpj' => preg_replace('/\D/', '', (string)$empresa['cnpj']),
                'ie' => (string)($empresa['inscricao_estadual'] ?? ''),
                'schemes' => 'PL_009_V4',
                'versao' => '4.00',
                'tokenIBPT' => '',
                'CSC' => (string)($perfil['ambiente_nfce'] == 1
                    ? $perfil['csc_token_producao']
                    : $perfil['csc_token_homologacao']),
                'CSCid' => (string)($perfil['ambiente_nfce'] == 1
                    ? $perfil['csc_id_producao']
                    : $perfil['csc_id_homologacao']),
            ]);

            $tools = new Tools($configJson, $cert);
            $tools->model('65');
            $xmlAssinado = $tools->signNFe($xml);
            $resp = $tools->sefazEnviaLote([$xmlAssinado], 1, 1); // idLote=1, indSinc=1 (sincrono)

            $respXml = @simplexml_load_string($resp);
            $cStat = (string)($respXml->protNFe->infProt->cStat ?? $respXml->cStat ?? '');
            $nProt = (string)($respXml->protNFe->infProt->nProt ?? '');
            $chave = preg_replace('/^NFe/', '', (string)($respXml->protNFe->infProt->chNFe ?? ''));
            $motivo = (string)($respXml->protNFe->infProt->xMotivo ?? $respXml->xMotivo ?? '');

            $statusFinal = ($cStat === '100') ? 'autorizada' : 'rejeitada';
            $xmlAutorizado = null;
            if ($statusFinal === 'autorizada') {
                $xmlAutorizado = Complements::toAuthorize($xmlAssinado, $resp);
            }

            $this->atualizarPosTransmissao($nfceId, [
                'status' => $statusFinal,
                'protocolo' => $nProt,
                'chave' => $chave,
                'xml_enviado' => $xmlAssinado,
                'xml_retorno' => $resp,
                'xml_autorizado' => $xmlAutorizado,
                'motivo' => $motivo,
            ]);
            $this->pdo->commit();

            return [
                'ok' => $statusFinal === 'autorizada',
                'id' => $nfceId,
                'status' => $statusFinal,
                'chave_acesso' => $chave,
                'protocolo' => $nProt,
                'motivo' => $motivo,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new RuntimeException('Falha ao emitir NFC-e: ' . $e->getMessage(), 0, $e);
        }
    }

    private function buscarVendaComProdutos(int $vendaId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.id, v.numero, v.valor_total, v.created_at, v.cliente_id, v.forma_pagamento_id,
                   c.nome AS cliente_nome, c.cpf_cnpj AS cliente_cpf_cnpj,
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
            SELECT i.*, p.nome AS produto_nome, p.codigo,
                   pf.ncm, pf.cest, pf.cfop, pf.origem, pf.csosn_cst,
                   pf.cst_pis, pf.cst_cofins, pf.aliquota_icms, pf.aliquota_ipi,
                   pf.aliquota_pis, pf.aliquota_cofins,
                   p.unidade AS unidade_medida
              FROM pdv_venda_itens i
              LEFT JOIN produtos p ON p.id = i.produto_id
              LEFT JOIN produto_fiscal pf ON pf.produto_id = i.produto_id
             WHERE i.venda_id = :id
               AND UPPER(i.tipo_item) = 'PRODUTO'
        ");
        $stmt2->execute([':id' => $vendaId]);
        $venda['itens_produto'] = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $venda;
    }

    private function validar(array $venda, array $empresa, array $perfil): void
    {
        $erros = [];
        if (empty($empresa['cnpj'])) $erros[] = 'CNPJ da empresa nao cadastrado.';
        if (empty($empresa['inscricao_estadual'])) $erros[] = 'IE da empresa nao cadastrada.';
        if (empty($empresa['certificado_path'])) $erros[] = 'Certificado A1 nao configurado.';
        $csc = ($perfil['ambiente_nfce'] == 1)
            ? ($perfil['csc_token_producao'] ?? '')
            : ($perfil['csc_token_homologacao'] ?? '');
        if (empty($csc)) $erros[] = 'CSC do ambiente NFC-e nao configurado.';

        foreach ($venda['itens_produto'] as $it) {
            if (empty($it['ncm'])) {
                $erros[] = 'Produto "' . $it['produto_nome'] . '" sem NCM em produto_fiscal.';
            }
            if (empty($it['cfop'])) {
                $erros[] = 'Produto "' . $it['produto_nome'] . '" sem CFOP em produto_fiscal.';
            }
        }
        if ($erros) throw new RuntimeException(implode(' | ', $erros));
    }

    private function montarIde(Make $make, array $venda, array $empresa, array $perfil, int $numero): void
    {
        $std = new \stdClass();
        $std->cUF = $this->codigoUf((string)$empresa['uf']);
        $std->cNF = str_pad((string)random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        $std->natOp = 'VENDA';
        $std->mod = 65;
        $std->serie = (int)$perfil['serie_nfce'];
        $std->nNF = $numero;
        $std->dhEmi = date('Y-m-d\TH:i:sP');
        $std->tpNF = 1;
        $std->idDest = ($venda['uf'] ?? $empresa['uf']) === $empresa['uf'] ? 1 : 2;
        $std->cMunFG = (string)($empresa['codigo_municipio'] ?? '');
        $std->tpImp = 4;
        $std->tpEmis = 1;
        $std->cDV = 0;
        $std->tpAmb = (int)$perfil['ambiente_nfce'];
        $std->finNFe = 1;
        $std->indFinal = 1;
        $std->indPres = 1;
        $std->procEmi = 0;
        $std->verProc = '1.0';
        $make->tagide($std);
    }

    private function montarEmit(Make $make, array $empresa): void
    {
        $std = new \stdClass();
        $std->xNome = (string)$empresa['nome'];
        $std->IE = (string)$empresa['inscricao_estadual'];
        $std->CRT = $this->crtFromRegime((string)($empresa['regime_tributario'] ?? '1'));
        $std->CNPJ = preg_replace('/\D/', '', (string)$empresa['cnpj']);
        $make->tagemit($std);

        $end = new \stdClass();
        $end->xLgr = (string)($empresa['logradouro'] ?? '');
        $end->nro = (string)($empresa['numero'] ?? '');
        $end->xBairro = (string)($empresa['bairro'] ?? '');
        $end->cMun = (string)($empresa['codigo_municipio'] ?? '');
        $end->xMun = (string)($empresa['cidade'] ?? '');
        $end->UF = strtoupper((string)($empresa['uf'] ?? ''));
        $end->CEP = preg_replace('/\D/', '', (string)($empresa['cep'] ?? ''));
        $end->cPais = '1058';
        $end->xPais = 'Brasil';
        $make->tagenderEmit($end);
    }

    private function montarDest(Make $make, array $venda): void
    {
        if (empty($venda['cliente_cpf_cnpj'])) return;
        $doc = preg_replace('/\D/', '', (string)$venda['cliente_cpf_cnpj']);
        $std = new \stdClass();
        $std->xNome = (string)$venda['cliente_nome'];
        $std->indIEDest = (int)($venda['ind_ie_dest'] ?? 9);
        if (strlen($doc) === 14) $std->CNPJ = $doc;
        else $std->CPF = $doc;
        $make->tagdest($std);
    }

    private function montarItem(Make $make, int $nItem, array $it, array $tributacao): void
    {
        $std = new \stdClass();
        $std->item = $nItem;
        $std->cProd = (string)($it['codigo'] ?? $it['produto_id']);
        $std->cEAN = 'SEM GTIN';
        $std->xProd = (string)$it['nome_item'];
        $std->NCM = preg_replace('/\D/', '', (string)$it['ncm']);
        $std->CFOP = (string)$it['cfop'];
        $std->uCom = (string)($it['unidade_medida'] ?? 'UN');
        $std->qCom = (float)$it['quantidade'];
        $std->vUnCom = (float)$it['valor_unitario'];
        $std->vProd = (float)$it['valor_total_item'];
        $std->cEANTrib = 'SEM GTIN';
        $std->uTrib = (string)($it['unidade_medida'] ?? 'UN');
        $std->qTrib = (float)$it['quantidade'];
        $std->vUnTrib = (float)$it['valor_unitario'];
        $std->indTot = 1;
        $make->tagprod($std);

        // ICMS (Simples Nacional — CSOSN)
        $icms = new \stdClass();
        $icms->item = $nItem;
        $icms->orig = (string)($it['origem'] ?? '0');
        $icms->CSOSN = (string)($it['csosn_cst'] ?? '102');
        $make->tagICMSSN($icms);

        // PIS
        $pis = new \stdClass();
        $pis->item = $nItem;
        $pis->CST = (string)($it['cst_pis'] ?? '49');
        $make->tagPIS($pis);

        // COFINS
        $cofins = new \stdClass();
        $cofins->item = $nItem;
        $cofins->CST = (string)($it['cst_cofins'] ?? '49');
        $make->tagCOFINS($cofins);
    }

    private function montarTotal(Make $make, array $venda): void
    {
        $std = new \stdClass();
        $std->vBC = 0;
        $std->vICMS = 0;
        $std->vICMSDeson = 0;
        $std->vFCP = 0;
        $std->vBCST = 0;
        $std->vST = 0;
        $std->vFCPST = 0;
        $std->vFCPSTRet = 0;
        $std->vProd = (float)$venda['valor_total'];
        $std->vFrete = 0;
        $std->vSeg = 0;
        $std->vDesc = 0;
        $std->vII = 0;
        $std->vIPI = 0;
        $std->vIPIDevol = 0;
        $std->vPIS = 0;
        $std->vCOFINS = 0;
        $std->vOutro = 0;
        $std->vNF = (float)$venda['valor_total'];
        $make->tagICMSTot($std);
    }

    private function montarTransp(Make $make): void
    {
        $std = new \stdClass();
        $std->modFrete = 9;
        $make->tagtransp($std);
    }

    private function montarPag(Make $make, array $venda): void
    {
        $stmt = $this->pdo->prepare('SELECT tipo FROM formas_pagamento WHERE id = :id');
        $stmt->execute([':id' => (int)$venda['forma_pagamento_id']]);
        $tipoFp = (string)$stmt->fetchColumn();
        $tPag = match ($tipoFp) {
            'D' => '01', 'CC' => '03', 'CD' => '04',
            'PIX' => '17', 'BOL' => '15', 'TB' => '18',
            default => '99',
        };
        $det = new \stdClass();
        $det->vTroco = 0;
        $make->tagpag($det);

        $pag = new \stdClass();
        $pag->tPag = $tPag;
        $pag->vPag = (float)$venda['valor_total'];
        $make->tagdetPag($pag);
    }

    private function codigoUf(string $uf): string
    {
        $map = ['AC'=>12,'AL'=>27,'AM'=>13,'AP'=>16,'BA'=>29,'CE'=>23,'DF'=>53,'ES'=>32,
                'GO'=>52,'MA'=>21,'MG'=>31,'MS'=>50,'MT'=>51,'PA'=>15,'PB'=>25,'PE'=>26,
                'PI'=>22,'PR'=>41,'RJ'=>33,'RN'=>24,'RO'=>11,'RR'=>14,'RS'=>43,'SC'=>42,
                'SE'=>28,'SP'=>35,'TO'=>17];
        return (string)($map[strtoupper($uf)] ?? 35);
    }

    private function crtFromRegime(string $regime): int
    {
        return match ($regime) {
            'simples_nacional' => 1,
            'lucro_presumido'  => 3,
            'lucro_real'       => 3,
            default            => (int)$regime ?: 1,
        };
    }

    private function gravarPendente(int $vendaId, int $numero, array $perfil, string $xml, float $valor): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO fiscal_nfce
                (venda_id, numero, serie, status, ambiente, valor_total, xml_enviado)
             VALUES
                (:v, :n, :s, 'pendente', :a, :vt, :xe)
             RETURNING id"
        );
        $stmt->execute([
            ':v' => $vendaId,
            ':n' => $numero,
            ':s' => (int)$perfil['serie_nfce'],
            ':a' => (int)$perfil['ambiente_nfce'],
            ':vt' => $valor,
            ':xe' => $xml,
        ]);
        return (int)$stmt->fetchColumn();
    }

    private function atualizarPosTransmissao(int $nfceId, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE fiscal_nfce
                SET status = :st,
                    protocolo = :pr,
                    chave_acesso = :ck,
                    xml_enviado = :xe,
                    xml_retorno = :xr,
                    xml_autorizado = :xa,
                    data_autorizacao = CASE WHEN :st2 = 'autorizada' THEN NOW() ELSE NULL END,
                    motivo_rejeicao = CASE WHEN :st3 <> 'autorizada' THEN :mt ELSE NULL END
              WHERE id = :id"
        );
        $stmt->execute([
            ':st' => $dados['status'],
            ':st2' => $dados['status'],
            ':st3' => $dados['status'],
            ':pr' => $dados['protocolo'] ?? null,
            ':ck' => $dados['chave'] ?? null,
            ':xe' => $dados['xml_enviado'] ?? null,
            ':xr' => $dados['xml_retorno'] ?? null,
            ':xa' => $dados['xml_autorizado'] ?? null,
            ':mt' => $dados['motivo'] ?? null,
            ':id' => $nfceId,
        ]);
    }

    public function listarNfceVenda(int $vendaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM fiscal_nfce WHERE venda_id = :v ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':v' => $vendaId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
}
