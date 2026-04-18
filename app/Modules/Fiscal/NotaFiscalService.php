<?php

namespace App\Modules\Fiscal;

use App\Support\AuditLogger;
use Exception;
use PDO;
use SimpleXMLElement;
use stdClass;
use Throwable;
use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\CertificateException;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;

/**
 * Service responsavel pelo fluxo de emissao e cancelamento de NF-e.
 */
class NotaFiscalService
{
    /**
     * @param PDO $pdo Conexao ativa com o banco.
     * @param NotaFiscalRepository $notaFiscalRepository Repositorio de NF-e.
     * @param EmpresaFiscalRepository $empresaFiscalRepository Repositorio da empresa emissora.
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly NotaFiscalRepository $notaFiscalRepository,
        private readonly EmpresaFiscalRepository $empresaFiscalRepository
    ) {}

    /**
     * Valida se um pedido pode seguir para emissao de NF-e.
     *
     * @param string $pedidoId ID do pedido.
     * @return array{valido: bool, erros: string[]}
     */
    public function validarPedidoParaEmissao(string $pedidoId): array
    {
        $erros = [];

        $stmtPedido = $this->pdo->prepare(
            "SELECT p.*, c.nome AS cliente_nome, c.cpf_cnpj, c.codigo_municipio
             FROM pedidos p
             LEFT JOIN clientes c ON c.id::text = p.cliente_id::text
             WHERE p.id = :id
             LIMIT 1"
        );
        $stmtPedido->execute([':id' => $pedidoId]);
        $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            return [
                'valido' => false,
                'erros' => ['Pedido nao encontrado.'],
            ];
        }

        if (strtoupper((string)($pedido['status'] ?? '')) !== 'FATURADO') {
            $erros[] = 'Pedido precisa estar com status FATURADO para emissao.';
        }

        $stmtNfe = $this->pdo->prepare(
            'SELECT numero_nfe
             FROM pedido_nfe
             WHERE pedido_id = :pedido_id
               AND status = :status
             ORDER BY data_emissao DESC NULLS LAST, numero_nfe DESC
             LIMIT 1'
        );
        $stmtNfe->execute([
            ':pedido_id' => $pedidoId,
            ':status' => NotaFiscal::STATUS_AUTORIZADA,
        ]);
        $nfeAutorizada = $stmtNfe->fetch(PDO::FETCH_ASSOC);

        if ($nfeAutorizada) {
            $erros[] = 'Pedido ja possui NF-e autorizada numero ' . (string)$nfeAutorizada['numero_nfe'] . '.';
        }

        if (empty($pedido['cpf_cnpj'])) {
            $erros[] = 'Cliente sem CPF/CNPJ preenchido.';
        }

        if (empty($pedido['codigo_municipio'])) {
            $erros[] = 'Cliente sem codigo do municipio preenchido.';
        }

        $stmtItens = $this->pdo->prepare(
            "SELECT pi.id,
                    COALESCE(pi.nome_produto, pr.nome, 'Produto sem nome') AS nome_produto,
                    pf.ncm,
                    pf.cfop,
                    pf.csosn_cst,
                    pf.cst_pis,
                    pf.cst_cofins,
                    pf.modalidade_bc_icms
             FROM pedido_itens pi
             LEFT JOIN produtos pr ON pr.id::text = pi.produto_id::text
             LEFT JOIN produto_fiscal pf ON pf.produto_id::text = pi.produto_id::text
             WHERE pi.pedido_id = :pedido_id
             ORDER BY pi.created_at ASC"
        );
        $stmtItens->execute([':pedido_id' => $pedidoId]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        if (!$itens) {
            $erros[] = 'Pedido sem itens para emissao.';
        }

        $camposObrigatorios = [
            'ncm' => 'NCM',
            'cfop' => 'CFOP',
            'csosn_cst' => 'CSOSN/CST',
            'cst_pis' => 'CST PIS',
            'cst_cofins' => 'CST COFINS',
            'modalidade_bc_icms' => 'Modalidade BC ICMS',
        ];

        foreach ($itens as $item) {
            $nomeProduto = (string)($item['nome_produto'] ?? 'Produto sem nome');

            foreach ($camposObrigatorios as $campo => $rotulo) {
                if (!isset($item[$campo]) || $item[$campo] === null || trim((string)$item[$campo]) === '') {
                    $erros[] = 'Produto "' . $nomeProduto . '" sem ' . $rotulo . ' cadastrado.';
                }
            }
        }

        $empresa = $this->empresaFiscalRepository->get();
        if ($empresa === null) {
            $erros[] = 'Empresa fiscal nao cadastrada em empresa_local.';
        } else {
            if ($empresa->ie === null || trim($empresa->ie) === '') {
                $erros[] = 'Empresa sem inscricao estadual preenchida.';
            }
            if ($empresa->certificado_path === null || trim($empresa->certificado_path) === '') {
                $erros[] = 'Empresa sem caminho do certificado digital preenchido.';
            }
            if ($empresa->ambiente_nfe === null || trim($empresa->ambiente_nfe) === '') {
                $erros[] = 'Empresa sem ambiente de NF-e preenchido.';
            }
        }

        return [
            'valido' => $erros === [],
            'erros' => $erros,
        ];
    }

    /**
     * Valida se um servico faturado com itens de produto pode seguir para emissao de NF-e.
     *
     * @param string $servicoId ID do servico.
     * @return array{valido: bool, erros: string[]}
     */
    public function validarServicoParaEmissao(string $servicoId): array
    {
        throw new \RuntimeException('Emissao de NF-e a partir de servicos esta desabilitada (auditoria 2026-04-18). Aguarde o novo mini-modulo de Servicos.');
        $erros = [];
        $servico = $this->buscarServicoFiscal($servicoId);

        if ($servico === null) {
            return [
                'valido' => false,
                'erros' => ['Servico nao encontrado.'],
            ];
        }

        if (strtoupper((string)($servico['status'] ?? '')) !== 'FATURADO') {
            $erros[] = 'Servico precisa estar com status FATURADO para emissao.';
        }

        $stmtNfe = $this->pdo->prepare(
            'SELECT numero_nfe
             FROM pedido_nfe
             WHERE servico_id = :servico_id
               AND status = :status
             ORDER BY data_emissao DESC NULLS LAST, numero_nfe DESC
             LIMIT 1'
        );
        $stmtNfe->execute([
            ':servico_id' => $servicoId,
            ':status' => NotaFiscal::STATUS_AUTORIZADA,
        ]);
        $nfeAutorizada = $stmtNfe->fetch(PDO::FETCH_ASSOC);

        if ($nfeAutorizada) {
            $erros[] = 'Servico ja possui NF-e autorizada numero ' . (string)$nfeAutorizada['numero_nfe'] . '.';
        }

        if (empty($servico['cpf_cnpj'])) {
            $erros[] = 'Cliente do servico sem CPF/CNPJ preenchido.';
        }

        if (empty($servico['codigo_municipio'])) {
            $erros[] = 'Cliente do servico sem codigo do municipio preenchido.';
        }

        $itens = $this->buscarItensProdutoServico($servicoId, $servico);
        if ($itens === []) {
            $erros[] = 'Servico sem itens de produto para emissao da NF-e.';
        }

        $camposObrigatorios = [
            'ncm' => 'NCM',
            'cfop' => 'CFOP',
            'csosn_cst' => 'CSOSN/CST',
            'cst_pis' => 'CST PIS',
            'cst_cofins' => 'CST COFINS',
            'modalidade_bc_icms' => 'Modalidade BC ICMS',
        ];

        foreach ($itens as $item) {
            $nomeProduto = (string)($item['nome_produto'] ?? 'Produto sem nome');

            foreach ($camposObrigatorios as $campo => $rotulo) {
                if (!isset($item[$campo]) || $item[$campo] === null || trim((string)$item[$campo]) === '') {
                    $erros[] = 'Produto "' . $nomeProduto . '" sem ' . $rotulo . ' cadastrado.';
                }
            }
        }

        $empresa = $this->empresaFiscalRepository->get();
        if ($empresa === null) {
            $erros[] = 'Empresa fiscal nao cadastrada em empresa_local.';
        } else {
            if ($empresa->ie === null || trim($empresa->ie) === '') {
                $erros[] = 'Empresa sem inscricao estadual preenchida.';
            }
            if ($empresa->certificado_path === null || trim($empresa->certificado_path) === '') {
                $erros[] = 'Empresa sem caminho do certificado digital preenchido.';
            }
            if ($empresa->ambiente_nfe === null || trim($empresa->ambiente_nfe) === '') {
                $erros[] = 'Empresa sem ambiente de NF-e preenchido.';
            }
        }

        return [
            'valido' => $erros === [],
            'erros' => $erros,
        ];
    }

    /**
     * Emite uma NF-e real via sped-nfe, transmite para a SEFAZ e persiste o XML autorizado.
     *
     * @param string $pedidoId ID do pedido faturado que sera convertido em NF-e.
     * @return array{sucesso: bool, nfe: ?NotaFiscal, erro: ?string}
     */
    public function emitir(string $pedidoId): array
    {
        $somenteDigitos = static fn (?string $valor): string => preg_replace('/\D+/', '', (string)$valor) ?? '';
        $texto = static fn (?string $valor, int $limite = 255): string => mb_substr(trim((string)$valor), 0, $limite);

        try {
            $this->pdo->beginTransaction();
            $validacao = $this->validarPedidoParaEmissao($pedidoId);
            if (!$validacao['valido']) {
                throw NotaFiscalException::fromErrors($validacao['erros']);
            }

            $empresa = $this->empresaFiscalRepository->get();
            if ($empresa === null) {
                throw new NotaFiscalException('Empresa fiscal nao cadastrada em empresa_local.');
            }

            $stmtPedido = $this->pdo->prepare(
                'SELECT p.id, p.numero, p.data_faturamento, p.valor_total, p.observacoes, p.cliente_id,
                        c.nome AS cliente_nome, c.cpf_cnpj, c.logradouro, c.numero_endereco, c.complemento,
                        c.bairro, c.cidade, c.estado, c.cep, c.codigo_municipio, c.ie, c.ind_ie_dest
                 FROM pedidos p
                 INNER JOIN clientes c ON c.id = p.cliente_id
                 WHERE p.id = :id
                 LIMIT 1'
            );
            $stmtPedido->execute([':id' => $pedidoId]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);
            if (!$pedido) {
                throw new NotaFiscalException('Pedido nao encontrado para emissao.');
            }

            $stmtItens = $this->pdo->prepare(
                "SELECT pi.produto_id, pi.nome_produto, pi.quantidade, pi.valor_unitario, pi.valor_total_item,
                        COALESCE(pr.unidade, 'UN') AS unidade, pf.ncm, pf.cest, pf.cfop, pf.csosn_cst,
                        pf.cst_pis, pf.cst_cofins, COALESCE(pf.origem, '0') AS origem,
                        COALESCE(pf.aliquota_icms, 0) AS aliquota_icms, COALESCE(pf.aliquota_ipi, 0) AS aliquota_ipi,
                        COALESCE(pf.aliquota_pis, 0) AS aliquota_pis, COALESCE(pf.aliquota_cofins, 0) AS aliquota_cofins,
                        pf.codigo_beneficio_fiscal, pf.ind_escala, pf.cnpj_fabricante, pf.modalidade_bc_icms,
                        COALESCE(pf.reducao_bc_icms, 0) AS reducao_bc_icms, COALESCE(pf.aliquota_icms_st, 0) AS aliquota_icms_st
                 FROM pedido_itens pi
                 LEFT JOIN produtos pr ON pr.id = pi.produto_id
                 LEFT JOIN produto_fiscal pf ON pf.produto_id = pi.produto_id
                 WHERE pi.pedido_id = :pedido_id
                 ORDER BY pi.created_at ASC"
            );
            $stmtItens->execute([':pedido_id' => $pedidoId]);
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
            if ($itens === []) {
                throw new NotaFiscalException('Pedido sem itens para emissao.');
            }

            $numero = $this->notaFiscalRepository->incrementarNumeroNfe();
            $config = [
                'atualizacao' => date('Y-m-d H:i:s'), 'tpAmb' => (int)$empresa->ambiente_nfe, 'razaosocial' => $empresa->nome,
                'siglaUF' => $empresa->uf, 'cnpj' => $empresa->cnpj, 'schemes' => 'PL_009_V4', 'versao' => '4.00',
                'tokenIBPT' => '', 'CSC' => '', 'CSCid' => '',
            ];
            $configJson = json_encode($config, JSON_THROW_ON_ERROR);
            $conteudoCertificado = file_get_contents((string)$empresa->certificado_path);
            if ($conteudoCertificado === false) {
                throw new NotaFiscalException('Nao foi possivel ler o certificado digital informado.');
            }
            $tools = new Tools($configJson, Certificate::readPfx($conteudoCertificado, (string)$empresa->certificado_senha));
            $tools->model('55');

            $ufMap = ['RO'=>11,'AC'=>12,'AM'=>13,'RR'=>14,'PA'=>15,'AP'=>16,'TO'=>17,'MA'=>21,'PI'=>22,'CE'=>23,'RN'=>24,'PB'=>25,'PE'=>26,'AL'=>27,'SE'=>28,'BA'=>29,'MG'=>31,'ES'=>32,'RJ'=>33,'SP'=>35,'PR'=>41,'SC'=>42,'RS'=>43,'MS'=>50,'MT'=>51,'GO'=>52,'DF'=>53];
            $make = new Make();
            $infNFe = new stdClass(); $infNFe->Id = ''; $infNFe->versao = '4.00'; $make->taginfNFe($infNFe);

            $ide = new stdClass();
            $ide->cUF = $ufMap[strtoupper((string)$empresa->uf)] ?? 35; $ide->cNF = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $ide->natOp = 'Venda'; $ide->mod = 55; $ide->serie = (int)$empresa->serie_nfe; $ide->nNF = $numero; $ide->dhEmi = date('c');
            $ide->dhSaiEnt = null; $ide->tpNF = 1; $ide->idDest = 1; $ide->cMunFG = (int)$somenteDigitos((string)$empresa->codigo_municipio);
            $ide->tpImp = 1; $ide->tpEmis = 1; $ide->cDV = null; $ide->tpAmb = (int)$empresa->ambiente_nfe; $ide->finNFe = 1;
            $ide->indFinal = 1; $ide->indPres = 9; $ide->procEmi = 0; $ide->verProc = '1.0'; $ide->dhCont = null; $ide->xJust = null;
            $make->tagide($ide);

            $emit = new stdClass();
            $emit->xNome = $texto($empresa->nome, 60); $emit->xFant = ''; $emit->IE = $somenteDigitos((string)$empresa->ie);
            $emit->IEST = null; $emit->IM = null; $emit->CNAE = null; $emit->CRT = (int)$empresa->regime_tributario;
            $emit->CNPJ = $somenteDigitos((string)$empresa->cnpj); $emit->CPF = null; $make->tagemit($emit);

            $enderEmit = new stdClass();
            $enderEmit->xLgr = $texto($empresa->logradouro, 60); $enderEmit->nro = $texto($empresa->numero, 60); $enderEmit->xCpl = $texto($empresa->complemento, 60);
            $enderEmit->xBairro = $texto($empresa->bairro, 60); $enderEmit->cMun = (int)$somenteDigitos((string)$empresa->codigo_municipio);
            $enderEmit->xMun = $texto($empresa->cidade, 60); $enderEmit->UF = strtoupper((string)$empresa->uf); $enderEmit->CEP = $somenteDigitos((string)$empresa->cep);
            $enderEmit->cPais = 1058; $enderEmit->xPais = 'Brasil'; $enderEmit->fone = null; $make->tagenderEmit($enderEmit);

            $dest = new stdClass();
            $documentoCliente = $somenteDigitos((string)$pedido['cpf_cnpj']);
            $dest->xNome = $texto((string)$pedido['cliente_nome'], 60); $dest->CNPJ = strlen($documentoCliente) === 14 ? $documentoCliente : null;
            $dest->CPF = strlen($documentoCliente) === 11 ? $documentoCliente : null; $dest->idEstrangeiro = null; $dest->indIEDest = (string)($pedido['ind_ie_dest'] ?? '9');
            $dest->IE = ((string)($pedido['ind_ie_dest'] ?? '9') === '1') ? $somenteDigitos((string)$pedido['ie']) : null; $dest->ISUF = null; $dest->IM = null; $dest->email = null;
            $make->tagdest($dest);

            $enderDest = new stdClass();
            $enderDest->xLgr = $texto((string)$pedido['logradouro'], 60); $enderDest->nro = $texto((string)$pedido['numero_endereco'], 60); $enderDest->xCpl = $texto((string)$pedido['complemento'], 60);
            $enderDest->xBairro = $texto((string)$pedido['bairro'], 60); $enderDest->cMun = (int)$somenteDigitos((string)$pedido['codigo_municipio']);
            $enderDest->xMun = $texto((string)$pedido['cidade'], 60); $enderDest->UF = strtoupper((string)$pedido['estado']); $enderDest->CEP = $somenteDigitos((string)$pedido['cep']);
            $enderDest->cPais = 1058; $enderDest->xPais = 'Brasil'; $enderDest->fone = null; $make->tagenderDest($enderDest);

            $totais = ['vBC'=>0.0,'vICMS'=>0.0,'vIPI'=>0.0,'vPIS'=>0.0,'vCOFINS'=>0.0,'vProd'=>0.0];
            $regimeTributario = (int)$empresa->regime_tributario;
            foreach ($itens as $indice => $item) {
                $itemNumero = $indice + 1; $valorItem = round((float)$item['valor_total_item'], 2); $quantidade = round((float)$item['quantidade'], 4);
                $valorUnitario = round((float)$item['valor_unitario'], 10); $aliquotaIcms = round((float)$item['aliquota_icms'], 4); $aliquotaIpi = round((float)$item['aliquota_ipi'], 4);
                $aliquotaPis = round((float)$item['aliquota_pis'], 4); $aliquotaCofins = round((float)$item['aliquota_cofins'], 4); $reducaoBcIcms = round((float)$item['reducao_bc_icms'], 4);
                $baseIcms = round($valorItem * (1 - ($reducaoBcIcms / 100)), 2); $valorIcms = round($baseIcms * ($aliquotaIcms / 100), 2); $valorIpi = $aliquotaIpi > 0 ? round($valorItem * ($aliquotaIpi / 100), 2) : 0.0;
                $valorPis = in_array((string)$item['cst_pis'], ['01', '02'], true) ? round($valorItem * ($aliquotaPis / 100), 2) : 0.0;
                $valorCofins = in_array((string)$item['cst_cofins'], ['01', '02'], true) ? round($valorItem * ($aliquotaCofins / 100), 2) : 0.0;

                $produto = new stdClass();
                $produto->item = $itemNumero; $produto->cProd = (string)$item['produto_id']; $produto->cEAN = 'SEM GTIN'; $produto->cBarra = 'SEM GTIN';
                $produto->xProd = $texto((string)$item['nome_produto'], 120); $produto->NCM = str_pad($somenteDigitos((string)$item['ncm']), 8, '0', STR_PAD_LEFT);
                $produto->CEST = !empty($item['cest']) ? $somenteDigitos((string)$item['cest']) : null; $produto->indEscala = !empty($item['ind_escala']) ? strtoupper((string)$item['ind_escala']) : null;
                $produto->CNPJFab = !empty($item['cnpj_fabricante']) ? $somenteDigitos((string)$item['cnpj_fabricante']) : null; $produto->cBenef = !empty($item['codigo_beneficio_fiscal']) ? $texto((string)$item['codigo_beneficio_fiscal'], 10) : null;
                $produto->EXTIPI = null; $produto->CFOP = (int)$item['cfop']; $produto->uCom = $texto((string)$item['unidade'], 6); $produto->qCom = $quantidade;
                $produto->vUnCom = $valorUnitario; $produto->vProd = $valorItem; $produto->cEANTrib = 'SEM GTIN'; $produto->uTrib = $texto((string)$item['unidade'], 6);
                $produto->qTrib = $quantidade; $produto->vUnTrib = $valorUnitario; $produto->vFrete = null; $produto->vSeg = null; $produto->vDesc = null; $produto->vOutro = null;
                $produto->indTot = 1; $produto->xPed = null; $produto->nItemPed = null; $produto->nFCI = null; $produto->vItem = null; $make->tagprod($produto);

                $imposto = new stdClass(); $imposto->item = $itemNumero; $imposto->vTotTrib = 0; $make->tagimposto($imposto);
                if (in_array($regimeTributario, [1, 2], true)) {
                    $icmsSn = new stdClass();
                    $icmsSn->item = $itemNumero; $icmsSn->orig = (int)$item['origem']; $icmsSn->CSOSN = str_pad((string)$item['csosn_cst'], 3, '0', STR_PAD_LEFT);
                    $icmsSn->pCredSN = null; $icmsSn->vCredICMSSN = null; $icmsSn->modBCST = !empty($item['modalidade_bc_icms']) ? (string)$item['modalidade_bc_icms'] : null;
                    $icmsSn->pMVAST = null; $icmsSn->pRedBCST = $reducaoBcIcms > 0 ? $reducaoBcIcms : null; $icmsSn->vBCST = null; $icmsSn->pICMSST = (float)$item['aliquota_icms_st'] > 0 ? round((float)$item['aliquota_icms_st'], 4) : null;
                    $icmsSn->vICMSST = null; $icmsSn->vBCFCPST = null; $icmsSn->pFCPST = null; $icmsSn->vFCPST = null; $icmsSn->vBCSTRet = null; $icmsSn->pST = null; $icmsSn->vICMSSTRet = null; $icmsSn->vBCFCPSTRet = null;
                    $icmsSn->pFCPSTRet = null; $icmsSn->vFCPSTRet = null; $icmsSn->modBC = !empty($item['modalidade_bc_icms']) ? (string)$item['modalidade_bc_icms'] : null; $icmsSn->vBC = null; $icmsSn->pRedBC = $reducaoBcIcms > 0 ? $reducaoBcIcms : null;
                    $icmsSn->pICMS = $aliquotaIcms > 0 ? $aliquotaIcms : null; $icmsSn->vICMS = null; $icmsSn->pRedBCEfet = null; $icmsSn->vBCEfet = null; $icmsSn->pICMSEfet = null; $icmsSn->vICMSEfet = null; $icmsSn->vICMSSubstituto = null;
                    $make->tagICMSSN($icmsSn);
                } else {
                    $icms = new stdClass();
                    $icms->item = $itemNumero; $icms->orig = (int)$item['origem']; $icms->CST = str_pad(substr((string)$item['csosn_cst'], -2), 2, '0', STR_PAD_LEFT);
                    $icms->modBC = (string)$item['modalidade_bc_icms']; $icms->vBC = $baseIcms; $icms->pICMS = $aliquotaIcms; $icms->vICMS = $valorIcms;
                    $icms->pFCP = null; $icms->vFCP = null; $icms->vBCFCP = null; $icms->modBCST = null; $icms->pMVAST = null; $icms->pRedBCST = null; $icms->vBCST = null; $icms->pICMSST = null; $icms->vICMSST = null;
                    $icms->vBCFCPST = null; $icms->pFCPST = null; $icms->vFCPST = null; $icms->vICMSDeson = null; $icms->motDesICMS = null; $icms->pRedBC = $reducaoBcIcms > 0 ? $reducaoBcIcms : null;
                    $icms->vICMSOp = null; $icms->pDif = null; $icms->vICMSDif = null; $icms->vBCSTRet = null; $icms->pST = null; $icms->vICMSSTRet = null; $icms->vBCFCPSTRet = null; $icms->pFCPSTRet = null; $icms->vFCPSTRet = null;
                    $icms->pRedBCEfet = null; $icms->vBCEfet = null; $icms->pICMSEfet = null; $icms->vICMSEfet = null; $icms->vICMSSubstituto = null; $make->tagICMS($icms);
                    $totais['vBC'] += $baseIcms; $totais['vICMS'] += $valorIcms;
                }

                if ($valorIpi > 0) {
                    $ipi = new stdClass(); $ipi->item = $itemNumero; $ipi->clEnq = null; $ipi->CNPJProd = null; $ipi->cSelo = null; $ipi->qSelo = null; $ipi->cEnq = '999';
                    $ipi->CST = '50'; $ipi->vBC = $valorItem; $ipi->pIPI = $aliquotaIpi; $ipi->vIPI = $valorIpi; $ipi->qUnid = null; $ipi->vUnid = null; $make->tagIPI($ipi); $totais['vIPI'] += $valorIpi;
                }

                $pis = new stdClass(); $pis->item = $itemNumero; $pis->CST = str_pad((string)$item['cst_pis'], 2, '0', STR_PAD_LEFT);
                $pis->vBC = in_array($pis->CST, ['01', '02', '99'], true) ? $valorItem : null; $pis->pPIS = in_array($pis->CST, ['01', '02', '99'], true) ? $aliquotaPis : null;
                $pis->vPIS = in_array($pis->CST, ['01', '02'], true) ? $valorPis : 0.0; $pis->qBCProd = null; $pis->vAliqProd = null; $make->tagPIS($pis); $totais['vPIS'] += $valorPis;

                $cofins = new stdClass(); $cofins->item = $itemNumero; $cofins->CST = str_pad((string)$item['cst_cofins'], 2, '0', STR_PAD_LEFT);
                $cofins->vBC = in_array($cofins->CST, ['01', '02', '99'], true) ? $valorItem : null; $cofins->pCOFINS = in_array($cofins->CST, ['01', '02', '99'], true) ? $aliquotaCofins : null;
                $cofins->vCOFINS = in_array($cofins->CST, ['01', '02'], true) ? $valorCofins : 0.0; $cofins->qBCProd = null; $cofins->vAliqProd = null; $make->tagCOFINS($cofins); $totais['vCOFINS'] += $valorCofins;
                $totais['vProd'] += $valorItem;
            }

            $icmsTot = new stdClass();
            $icmsTot->vBC = round($totais['vBC'], 2); $icmsTot->vICMS = round($totais['vICMS'], 2); $icmsTot->vICMSDeson = 0; $icmsTot->vFCPUFDest = 0; $icmsTot->vICMSUFDest = 0; $icmsTot->vICMSUFRemet = 0;
            $icmsTot->vFCP = 0; $icmsTot->vBCST = 0; $icmsTot->vST = 0; $icmsTot->vFCPST = 0; $icmsTot->vFCPSTRet = 0; $icmsTot->vProd = round($totais['vProd'], 2); $icmsTot->vFrete = 0; $icmsTot->vSeg = 0;
            $icmsTot->vDesc = 0; $icmsTot->vII = 0; $icmsTot->vIPI = round($totais['vIPI'], 2); $icmsTot->vIPIDevol = 0; $icmsTot->vPIS = round($totais['vPIS'], 2); $icmsTot->vCOFINS = round($totais['vCOFINS'], 2);
            $icmsTot->vOutro = 0; $icmsTot->vNF = round((float)$pedido['valor_total'], 2); $icmsTot->vTotTrib = 0; $make->tagICMSTot($icmsTot);

            $transp = new stdClass(); $transp->modFrete = 9; $make->tagtransp($transp);
            $pag = new stdClass(); $pag->vTroco = 0; $make->tagpag($pag);
            $detPag = new stdClass(); $detPag->indPag = 0; $detPag->tPag = '01'; $detPag->xPag = null; $detPag->vPag = round((float)$pedido['valor_total'], 2); $make->tagdetPag($detPag);
            if (!empty($pedido['observacoes'])) { $infAdic = new stdClass(); $infAdic->infAdFisco = ''; $infAdic->infCpl = $texto((string)$pedido['observacoes'], 5000); $make->taginfAdic($infAdic); }

            $xml = $make->montaNFe();
            $chaveAcesso = $somenteDigitos((string)$make->chNFe);
            $nfe = $this->notaFiscalRepository->create([
                'pedido_id' => $pedidoId, 'numero_nfe' => $numero, 'chave_acesso' => $chaveAcesso,
                'status' => NotaFiscal::STATUS_PENDENTE, 'data_emissao' => date('Y-m-d H:i:s'), 'xml_nfe' => null,
            ]);

            $xmlAssinado = $tools->signNFe($xml);
            $response = $tools->sefazEnviaLote([$xmlAssinado], str_pad((string)$numero, 15, '0', STR_PAD_LEFT), 1);
            $responseXml = simplexml_load_string($response);
            if (!$responseXml instanceof SimpleXMLElement) {
                throw new NotaFiscalException('Resposta invalida da SEFAZ.');
            }
            $retEnviNFe = $responseXml->xpath('//*[local-name()="retEnviNFe" or local-name()="retConsReciNFe"]');
            $retorno = $retEnviNFe[0] ?? $responseXml;
            $cStat = trim((string)(($retorno->xpath('./*[local-name()="cStat"]')[0] ?? null) ?: ''));
            $xMotivo = trim((string)(($retorno->xpath('./*[local-name()="xMotivo"]')[0] ?? null) ?: ''));
            $chNFe = null; $nProt = null;
            if ($cStat === '100') {
                $chNFe = trim((string)(($retorno->xpath('./*[local-name()="chNFe"]')[0] ?? null) ?: ''));
                $nProt = trim((string)(($retorno->xpath('./*[local-name()="nProt"]')[0] ?? null) ?: ''));
            } elseif ($cStat === '104') {
                $infProt = $retorno->xpath('./*[local-name()="protNFe"]/*[local-name()="infProt"]'); $infProtNode = $infProt[0] ?? null;
                $cStatProt = trim((string)(($infProtNode?->xpath('./*[local-name()="cStat"]')[0] ?? null) ?: ''));
                $xMotivoProt = trim((string)(($infProtNode?->xpath('./*[local-name()="xMotivo"]')[0] ?? null) ?: ''));
                if ($cStatProt !== '100') { throw new NotaFiscalException($xMotivoProt !== '' ? $xMotivoProt : 'NF-e rejeitada pela SEFAZ.'); }
                $chNFe = trim((string)(($infProtNode?->xpath('./*[local-name()="chNFe"]')[0] ?? null) ?: ''));
                $nProt = trim((string)(($infProtNode?->xpath('./*[local-name()="nProt"]')[0] ?? null) ?: ''));
            } else {
                throw new NotaFiscalException($xMotivo !== '' ? $xMotivo : 'NF-e rejeitada pela SEFAZ.');
            }
            if ($chNFe === null || $chNFe === '' || $nProt === null || $nProt === '') {
                throw new NotaFiscalException('Resposta da SEFAZ sem chave de acesso ou protocolo de autorizacao.');
            }

            $xmlAutorizado = Complements::toAuthorize($xmlAssinado, $response);
            $stmtAtualizaNota = $this->pdo->prepare(
                'UPDATE pedido_nfe SET status = :status, chave_acesso = :chave_acesso, n_prot = :n_prot, xml_nfe = :xml_nfe, updated_at = NOW() WHERE id = :id'
            );
            $stmtAtualizaNota->execute([':status' => NotaFiscal::STATUS_AUTORIZADA, ':chave_acesso' => $chNFe, ':n_prot' => $nProt, ':xml_nfe' => $xmlAutorizado, ':id' => $nfe->id]);
            $stmtPedidoEmitido = $this->pdo->prepare('UPDATE pedidos SET nfe_emitida = TRUE, updated_at = NOW() WHERE id = :id');
            $stmtPedidoEmitido->execute([':id' => $pedidoId]);
            $this->pdo->commit();

            return ['sucesso' => true, 'nfe' => $this->notaFiscalRepository->findById($nfe->id), 'erro' => null];
        } catch (CertificateException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            $this->registrarLogErro($pedidoId, $e->getMessage());
            return ['sucesso' => false, 'nfe' => null, 'erro' => 'Certificado digital invalido ou vencido'];
        } catch (NotaFiscalException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            $this->registrarLogErro($pedidoId, $e->getMessage());
            return ['sucesso' => false, 'nfe' => null, 'erro' => $e->getMessage()];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            $this->registrarLogErro($pedidoId, $e->getMessage());
            return ['sucesso' => false, 'nfe' => null, 'erro' => 'Falha na comunicacao com a SEFAZ'];
        }
    }

    /**
     * Emite uma NF-e de produto para os itens de produto vinculados a um servico faturado.
     *
     * @param string $servicoId ID do servico faturado.
     * @return array{sucesso: bool, nfe: ?NotaFiscal, erro: ?string}
     */
    public function emitirServico(string $servicoId): array
    {
        throw new \RuntimeException('Emissao de NF-e a partir de servicos esta desabilitada (auditoria 2026-04-18). Aguarde o novo mini-modulo de Servicos.');
        $somenteDigitos = static fn (?string $valor): string => preg_replace('/\D+/', '', (string)$valor) ?? '';
        $texto = static fn (?string $valor, int $limite = 255): string => mb_substr(trim((string)$valor), 0, $limite);

        try {
            $this->pdo->beginTransaction();
            $validacao = $this->validarServicoParaEmissao($servicoId);
            if (!$validacao['valido']) {
                throw NotaFiscalException::fromErrors($validacao['erros']);
            }

            $empresa = $this->empresaFiscalRepository->get();
            if ($empresa === null) {
                throw new NotaFiscalException('Empresa fiscal nao cadastrada em empresa_local.');
            }

            $servico = $this->buscarServicoFiscal($servicoId);
            if ($servico === null) {
                throw new NotaFiscalException('Servico nao encontrado para emissao.');
            }

            $itens = $this->buscarItensProdutoServico($servicoId, $servico);
            if ($itens === []) {
                throw new NotaFiscalException('Servico sem itens de produto para emissao.');
            }

            $numero = $this->notaFiscalRepository->incrementarNumeroNfe();
            $config = [
                'atualizacao' => date('Y-m-d H:i:s'), 'tpAmb' => (int)$empresa->ambiente_nfe, 'razaosocial' => $empresa->nome,
                'siglaUF' => $empresa->uf, 'cnpj' => $empresa->cnpj, 'schemes' => 'PL_009_V4', 'versao' => '4.00',
                'tokenIBPT' => '', 'CSC' => '', 'CSCid' => '',
            ];
            $configJson = json_encode($config, JSON_THROW_ON_ERROR);
            $conteudoCertificado = file_get_contents((string)$empresa->certificado_path);
            if ($conteudoCertificado === false) {
                throw new NotaFiscalException('Nao foi possivel ler o certificado digital informado.');
            }
            $tools = new Tools($configJson, Certificate::readPfx($conteudoCertificado, (string)$empresa->certificado_senha));
            $tools->model('55');

            $ufMap = ['RO'=>11,'AC'=>12,'AM'=>13,'RR'=>14,'PA'=>15,'AP'=>16,'TO'=>17,'MA'=>21,'PI'=>22,'CE'=>23,'RN'=>24,'PB'=>25,'PE'=>26,'AL'=>27,'SE'=>28,'BA'=>29,'MG'=>31,'ES'=>32,'RJ'=>33,'SP'=>35,'PR'=>41,'SC'=>42,'RS'=>43,'MS'=>50,'MT'=>51,'GO'=>52,'DF'=>53];
            $make = new Make();
            $infNFe = new stdClass(); $infNFe->Id = ''; $infNFe->versao = '4.00'; $make->taginfNFe($infNFe);

            $ide = new stdClass();
            $ide->cUF = $ufMap[strtoupper((string)$empresa->uf)] ?? 35; $ide->cNF = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $ide->natOp = 'Venda'; $ide->mod = 55; $ide->serie = (int)$empresa->serie_nfe; $ide->nNF = $numero; $ide->dhEmi = date('c');
            $ide->dhSaiEnt = null; $ide->tpNF = 1; $ide->idDest = 1; $ide->cMunFG = (int)$somenteDigitos((string)$empresa->codigo_municipio);
            $ide->tpImp = 1; $ide->tpEmis = 1; $ide->cDV = null; $ide->tpAmb = (int)$empresa->ambiente_nfe; $ide->finNFe = 1;
            $ide->indFinal = 1; $ide->indPres = 9; $ide->procEmi = 0; $ide->verProc = '1.0'; $ide->dhCont = null; $ide->xJust = null;
            $make->tagide($ide);

            $emit = new stdClass();
            $emit->xNome = $texto($empresa->nome, 60); $emit->xFant = ''; $emit->IE = $somenteDigitos((string)$empresa->ie);
            $emit->IEST = null; $emit->IM = null; $emit->CNAE = null; $emit->CRT = (int)$empresa->regime_tributario;
            $emit->CNPJ = $somenteDigitos((string)$empresa->cnpj); $emit->CPF = null; $make->tagemit($emit);

            $enderEmit = new stdClass();
            $enderEmit->xLgr = $texto($empresa->logradouro, 60); $enderEmit->nro = $texto($empresa->numero, 60); $enderEmit->xCpl = $texto($empresa->complemento, 60);
            $enderEmit->xBairro = $texto($empresa->bairro, 60); $enderEmit->cMun = (int)$somenteDigitos((string)$empresa->codigo_municipio);
            $enderEmit->xMun = $texto($empresa->cidade, 60); $enderEmit->UF = strtoupper((string)$empresa->uf); $enderEmit->CEP = $somenteDigitos((string)$empresa->cep);
            $enderEmit->cPais = 1058; $enderEmit->xPais = 'Brasil'; $enderEmit->fone = null; $make->tagenderEmit($enderEmit);

            $dest = new stdClass();
            $documentoCliente = $somenteDigitos((string)$servico['cpf_cnpj']);
            $dest->xNome = $texto((string)$servico['cliente_nome'], 60); $dest->CNPJ = strlen($documentoCliente) === 14 ? $documentoCliente : null;
            $dest->CPF = strlen($documentoCliente) === 11 ? $documentoCliente : null; $dest->idEstrangeiro = null; $dest->indIEDest = (string)($servico['ind_ie_dest'] ?? '9');
            $dest->IE = ((string)($servico['ind_ie_dest'] ?? '9') === '1') ? $somenteDigitos((string)$servico['ie']) : null; $dest->ISUF = null; $dest->IM = null; $dest->email = null;
            $make->tagdest($dest);

            $enderDest = new stdClass();
            $enderDest->xLgr = $texto((string)$servico['logradouro'], 60); $enderDest->nro = $texto((string)$servico['numero_endereco'], 60); $enderDest->xCpl = $texto((string)$servico['complemento'], 60);
            $enderDest->xBairro = $texto((string)$servico['bairro'], 60); $enderDest->cMun = (int)$somenteDigitos((string)$servico['codigo_municipio']);
            $enderDest->xMun = $texto((string)$servico['cidade'], 60); $enderDest->UF = strtoupper((string)$servico['estado']); $enderDest->CEP = $somenteDigitos((string)$servico['cep']);
            $enderDest->cPais = 1058; $enderDest->xPais = 'Brasil'; $enderDest->fone = null; $make->tagenderDest($enderDest);

            $totais = ['vBC'=>0.0,'vICMS'=>0.0,'vIPI'=>0.0,'vPIS'=>0.0,'vCOFINS'=>0.0,'vProd'=>0.0];
            $regimeTributario = (int)$empresa->regime_tributario;
            foreach ($itens as $indice => $item) {
                $itemNumero = $indice + 1; $valorItem = round((float)$item['valor_total_item'], 2); $quantidade = round((float)$item['quantidade'], 4);
                $valorUnitario = round((float)$item['valor_unitario'], 10); $aliquotaIcms = round((float)$item['aliquota_icms'], 4); $aliquotaIpi = round((float)$item['aliquota_ipi'], 4);
                $aliquotaPis = round((float)$item['aliquota_pis'], 4); $aliquotaCofins = round((float)$item['aliquota_cofins'], 4); $reducaoBcIcms = round((float)$item['reducao_bc_icms'], 4);
                $baseIcms = round($valorItem * (1 - ($reducaoBcIcms / 100)), 2); $valorIcms = round($baseIcms * ($aliquotaIcms / 100), 2); $valorIpi = $aliquotaIpi > 0 ? round($valorItem * ($aliquotaIpi / 100), 2) : 0.0;
                $valorPis = in_array((string)$item['cst_pis'], ['01', '02'], true) ? round($valorItem * ($aliquotaPis / 100), 2) : 0.0;
                $valorCofins = in_array((string)$item['cst_cofins'], ['01', '02'], true) ? round($valorItem * ($aliquotaCofins / 100), 2) : 0.0;

                $produto = new stdClass();
                $produto->item = $itemNumero; $produto->cProd = (string)$item['produto_id']; $produto->cEAN = 'SEM GTIN'; $produto->cBarra = 'SEM GTIN';
                $produto->xProd = $texto((string)$item['nome_produto'], 120); $produto->NCM = str_pad($somenteDigitos((string)$item['ncm']), 8, '0', STR_PAD_LEFT);
                $produto->CEST = !empty($item['cest']) ? $somenteDigitos((string)$item['cest']) : null; $produto->indEscala = !empty($item['ind_escala']) ? strtoupper((string)$item['ind_escala']) : null;
                $produto->CNPJFab = !empty($item['cnpj_fabricante']) ? $somenteDigitos((string)$item['cnpj_fabricante']) : null; $produto->cBenef = !empty($item['codigo_beneficio_fiscal']) ? $texto((string)$item['codigo_beneficio_fiscal'], 10) : null;
                $produto->EXTIPI = null; $produto->CFOP = (int)$item['cfop']; $produto->uCom = $texto((string)$item['unidade'], 6); $produto->qCom = $quantidade;
                $produto->vUnCom = $valorUnitario; $produto->vProd = $valorItem; $produto->cEANTrib = 'SEM GTIN'; $produto->uTrib = $texto((string)$item['unidade'], 6);
                $produto->qTrib = $quantidade; $produto->vUnTrib = $valorUnitario; $produto->vFrete = null; $produto->vSeg = null; $produto->vDesc = null; $produto->vOutro = null;
                $produto->indTot = 1; $produto->xPed = null; $produto->nItemPed = null; $produto->nFCI = null; $produto->vItem = null; $make->tagprod($produto);

                $imposto = new stdClass(); $imposto->item = $itemNumero; $imposto->vTotTrib = 0; $make->tagimposto($imposto);
                if (in_array($regimeTributario, [1, 2], true)) {
                    $icmsSn = new stdClass();
                    $icmsSn->item = $itemNumero; $icmsSn->orig = (int)$item['origem']; $icmsSn->CSOSN = str_pad((string)$item['csosn_cst'], 3, '0', STR_PAD_LEFT);
                    $icmsSn->pCredSN = null; $icmsSn->vCredICMSSN = null; $icmsSn->modBCST = !empty($item['modalidade_bc_icms']) ? (string)$item['modalidade_bc_icms'] : null;
                    $icmsSn->pMVAST = null; $icmsSn->pRedBCST = $reducaoBcIcms > 0 ? $reducaoBcIcms : null; $icmsSn->vBCST = null; $icmsSn->pICMSST = (float)$item['aliquota_icms_st'] > 0 ? round((float)$item['aliquota_icms_st'], 4) : null;
                    $icmsSn->vICMSST = null; $icmsSn->vBCFCPST = null; $icmsSn->pFCPST = null; $icmsSn->vFCPST = null; $icmsSn->vBCSTRet = null; $icmsSn->pST = null; $icmsSn->vICMSSTRet = null; $icmsSn->vBCFCPSTRet = null;
                    $icmsSn->pFCPSTRet = null; $icmsSn->vFCPSTRet = null; $icmsSn->modBC = !empty($item['modalidade_bc_icms']) ? (string)$item['modalidade_bc_icms'] : null; $icmsSn->vBC = null; $icmsSn->pRedBC = $reducaoBcIcms > 0 ? $reducaoBcIcms : null;
                    $icmsSn->pICMS = $aliquotaIcms > 0 ? $aliquotaIcms : null; $icmsSn->vICMS = null; $icmsSn->pRedBCEfet = null; $icmsSn->vBCEfet = null; $icmsSn->pICMSEfet = null; $icmsSn->vICMSEfet = null; $icmsSn->vICMSSubstituto = null;
                    $make->tagICMSSN($icmsSn);
                } else {
                    $icms = new stdClass();
                    $icms->item = $itemNumero; $icms->orig = (int)$item['origem']; $icms->CST = str_pad(substr((string)$item['csosn_cst'], -2), 2, '0', STR_PAD_LEFT);
                    $icms->modBC = (string)$item['modalidade_bc_icms']; $icms->vBC = $baseIcms; $icms->pICMS = $aliquotaIcms; $icms->vICMS = $valorIcms;
                    $icms->pFCP = null; $icms->vFCP = null; $icms->vBCFCP = null; $icms->modBCST = null; $icms->pMVAST = null; $icms->pRedBCST = null; $icms->vBCST = null; $icms->pICMSST = null; $icms->vICMSST = null;
                    $icms->vBCFCPST = null; $icms->pFCPST = null; $icms->vFCPST = null; $icms->vICMSDeson = null; $icms->motDesICMS = null; $icms->pRedBC = $reducaoBcIcms > 0 ? $reducaoBcIcms : null;
                    $icms->vICMSOp = null; $icms->pDif = null; $icms->vICMSDif = null; $icms->vBCSTRet = null; $icms->pST = null; $icms->vICMSSTRet = null; $icms->vBCFCPSTRet = null; $icms->pFCPSTRet = null; $icms->vFCPSTRet = null;
                    $icms->pRedBCEfet = null; $icms->vBCEfet = null; $icms->pICMSEfet = null; $icms->vICMSEfet = null; $icms->vICMSSubstituto = null; $make->tagICMS($icms);
                    $totais['vBC'] += $baseIcms; $totais['vICMS'] += $valorIcms;
                }

                if ($valorIpi > 0) {
                    $ipi = new stdClass(); $ipi->item = $itemNumero; $ipi->clEnq = null; $ipi->CNPJProd = null; $ipi->cSelo = null; $ipi->qSelo = null; $ipi->cEnq = '999';
                    $ipi->CST = '50'; $ipi->vBC = $valorItem; $ipi->pIPI = $aliquotaIpi; $ipi->vIPI = $valorIpi; $ipi->qUnid = null; $ipi->vUnid = null; $make->tagIPI($ipi); $totais['vIPI'] += $valorIpi;
                }

                $pis = new stdClass(); $pis->item = $itemNumero; $pis->CST = str_pad((string)$item['cst_pis'], 2, '0', STR_PAD_LEFT);
                $pis->vBC = in_array($pis->CST, ['01', '02', '99'], true) ? $valorItem : null; $pis->pPIS = in_array($pis->CST, ['01', '02', '99'], true) ? $aliquotaPis : null;
                $pis->vPIS = in_array($pis->CST, ['01', '02'], true) ? $valorPis : 0.0; $pis->qBCProd = null; $pis->vAliqProd = null; $make->tagPIS($pis); $totais['vPIS'] += $valorPis;

                $cofins = new stdClass(); $cofins->item = $itemNumero; $cofins->CST = str_pad((string)$item['cst_cofins'], 2, '0', STR_PAD_LEFT);
                $cofins->vBC = in_array($cofins->CST, ['01', '02', '99'], true) ? $valorItem : null; $cofins->pCOFINS = in_array($cofins->CST, ['01', '02', '99'], true) ? $aliquotaCofins : null;
                $cofins->vCOFINS = in_array($cofins->CST, ['01', '02'], true) ? $valorCofins : 0.0; $cofins->qBCProd = null; $cofins->vAliqProd = null; $make->tagCOFINS($cofins); $totais['vCOFINS'] += $valorCofins;
                $totais['vProd'] += $valorItem;
            }

            $icmsTot = new stdClass();
            $icmsTot->vBC = round($totais['vBC'], 2); $icmsTot->vICMS = round($totais['vICMS'], 2); $icmsTot->vICMSDeson = 0; $icmsTot->vFCPUFDest = 0; $icmsTot->vICMSUFDest = 0; $icmsTot->vICMSUFRemet = 0;
            $icmsTot->vFCP = 0; $icmsTot->vBCST = 0; $icmsTot->vST = 0; $icmsTot->vFCPST = 0; $icmsTot->vFCPSTRet = 0; $icmsTot->vProd = round($totais['vProd'], 2); $icmsTot->vFrete = 0; $icmsTot->vSeg = 0;
            $icmsTot->vDesc = 0; $icmsTot->vII = 0; $icmsTot->vIPI = round($totais['vIPI'], 2); $icmsTot->vIPIDevol = 0; $icmsTot->vPIS = round($totais['vPIS'], 2); $icmsTot->vCOFINS = round($totais['vCOFINS'], 2);
            $icmsTot->vOutro = 0; $icmsTot->vNF = round(array_reduce($itens, static fn (float $carry, array $item): float => $carry + (float)$item['valor_total_item'], 0.0), 2); $icmsTot->vTotTrib = 0; $make->tagICMSTot($icmsTot);

            $transp = new stdClass(); $transp->modFrete = 9; $make->tagtransp($transp);
            $pag = new stdClass(); $pag->vTroco = 0; $make->tagpag($pag);
            $detPag = new stdClass(); $detPag->indPag = 0; $detPag->tPag = '90'; $detPag->xPag = 'Sem pagamento'; $detPag->vPag = round((float)$icmsTot->vNF, 2); $make->tagdetPag($detPag);
            if (!empty($servico['observacoes'])) { $infAdic = new stdClass(); $infAdic->infAdFisco = ''; $infAdic->infCpl = $texto((string)$servico['observacoes'], 5000); $make->taginfAdic($infAdic); }

            $xml = $make->montaNFe();
            $chaveAcesso = $somenteDigitos((string)$make->chNFe);
            $nfe = $this->notaFiscalRepository->create([
                'pedido_id' => null,
                'servico_id' => $servicoId,
                'numero_nfe' => $numero,
                'chave_acesso' => $chaveAcesso,
                'status' => NotaFiscal::STATUS_PENDENTE,
                'data_emissao' => date('Y-m-d H:i:s'),
                'xml_nfe' => null,
            ]);

            $xmlAssinado = $tools->signNFe($xml);
            $response = $tools->sefazEnviaLote([$xmlAssinado], str_pad((string)$numero, 15, '0', STR_PAD_LEFT), 1);
            $responseXml = simplexml_load_string($response);
            if (!$responseXml instanceof SimpleXMLElement) {
                throw new NotaFiscalException('Resposta invalida da SEFAZ.');
            }
            $retEnviNFe = $responseXml->xpath('//*[local-name()="retEnviNFe" or local-name()="retConsReciNFe"]');
            $retorno = $retEnviNFe[0] ?? $responseXml;
            $cStat = trim((string)(($retorno->xpath('./*[local-name()="cStat"]')[0] ?? null) ?: ''));
            $xMotivo = trim((string)(($retorno->xpath('./*[local-name()="xMotivo"]')[0] ?? null) ?: ''));
            $chNFe = null; $nProt = null;
            if ($cStat === '100') {
                $chNFe = trim((string)(($retorno->xpath('./*[local-name()="chNFe"]')[0] ?? null) ?: ''));
                $nProt = trim((string)(($retorno->xpath('./*[local-name()="nProt"]')[0] ?? null) ?: ''));
            } elseif ($cStat === '104') {
                $infProt = $retorno->xpath('./*[local-name()="protNFe"]/*[local-name()="infProt"]'); $infProtNode = $infProt[0] ?? null;
                $cStatProt = trim((string)(($infProtNode?->xpath('./*[local-name()="cStat"]')[0] ?? null) ?: ''));
                $xMotivoProt = trim((string)(($infProtNode?->xpath('./*[local-name()="xMotivo"]')[0] ?? null) ?: ''));
                if ($cStatProt !== '100') { throw new NotaFiscalException($xMotivoProt !== '' ? $xMotivoProt : 'NF-e rejeitada pela SEFAZ.'); }
                $chNFe = trim((string)(($infProtNode?->xpath('./*[local-name()="chNFe"]')[0] ?? null) ?: ''));
                $nProt = trim((string)(($infProtNode?->xpath('./*[local-name()="nProt"]')[0] ?? null) ?: ''));
            } else {
                throw new NotaFiscalException($xMotivo !== '' ? $xMotivo : 'NF-e rejeitada pela SEFAZ.');
            }
            if ($chNFe === null || $chNFe === '' || $nProt === null || $nProt === '') {
                throw new NotaFiscalException('Resposta da SEFAZ sem chave de acesso ou protocolo de autorizacao.');
            }

            $xmlAutorizado = Complements::toAuthorize($xmlAssinado, $response);
            $stmtAtualizaNota = $this->pdo->prepare(
                'UPDATE pedido_nfe SET status = :status, chave_acesso = :chave_acesso, n_prot = :n_prot, xml_nfe = :xml_nfe, updated_at = NOW() WHERE id = :id'
            );
            $stmtAtualizaNota->execute([':status' => NotaFiscal::STATUS_AUTORIZADA, ':chave_acesso' => $chNFe, ':n_prot' => $nProt, ':xml_nfe' => $xmlAutorizado, ':id' => $nfe->id]);
            $this->pdo->commit();

            (new AuditLogger($this->pdo))->registrar(
                'fiscal',
                'EMITIR_SERVICO',
                'nota_fiscal_produto',
                null,
                'NF-e de produto gerada a partir de servico faturado.',
                [
                    'servico_id' => $servicoId,
                    'nota_fiscal_id' => $nfe->id,
                    'numero_nfe' => $numero,
                ]
            );

            return ['sucesso' => true, 'nfe' => $this->notaFiscalRepository->findById($nfe->id), 'erro' => null];
        } catch (CertificateException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            $this->registrarLogErro('servico:' . $servicoId, $e->getMessage());
            return ['sucesso' => false, 'nfe' => null, 'erro' => 'Certificado digital invalido ou vencido'];
        } catch (NotaFiscalException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            $this->registrarLogErro('servico:' . $servicoId, $e->getMessage());
            return ['sucesso' => false, 'nfe' => null, 'erro' => $e->getMessage()];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            $this->registrarLogErro('servico:' . $servicoId, $e->getMessage());
            return ['sucesso' => false, 'nfe' => null, 'erro' => 'Falha na comunicacao com a SEFAZ'];
        }
    }

    /**
     * Cancela uma NF-e autorizada na SEFAZ usando a chave e o protocolo armazenados localmente.
     *
     * @param string $nfeId ID interno da NF-e autorizada.
     * @param string $justificativa Justificativa do cancelamento com no minimo 15 caracteres.
     * @return array{sucesso: bool, erro: ?string}
     */
    public function cancelar(string $nfeId, string $justificativa): array
    {
        $somenteDigitos = static fn (?string $valor): string => preg_replace('/\D+/', '', (string)$valor) ?? '';
        $justificativa = trim($justificativa);
        $pedidoIdLog = $nfeId;

        try {
            $nfe = $this->notaFiscalRepository->findById($nfeId);
            if ($nfe === null) {
                throw new NotaFiscalException('NF-e nao encontrada.');
            }
            $pedidoIdLog = $nfe->pedido_id ?? ($nfe->servico_id !== null ? 'servico:' . $nfe->servico_id : $nfeId);

            if (mb_strlen($justificativa) < 15) {
                throw new NotaFiscalException('Justificativa deve ter pelo menos 15 caracteres.');
            }
            if (!$nfe->isCancelavel()) {
                throw new NotaFiscalException('Prazo de cancelamento expirado');
            }

            $stmtNota = $this->pdo->prepare(
                'SELECT id, pedido_id, servico_id, chave_acesso, n_prot FROM pedido_nfe WHERE id = :id LIMIT 1'
            );
            $stmtNota->execute([':id' => $nfeId]);
            $notaPersistida = $stmtNota->fetch(PDO::FETCH_ASSOC);
            if (!$notaPersistida) {
                throw new NotaFiscalException('NF-e nao encontrada para cancelamento.');
            }
            if (empty($notaPersistida['chave_acesso']) || empty($notaPersistida['n_prot'])) {
                throw new NotaFiscalException('NF-e sem protocolo de autorizacao para cancelamento.');
            }

            $empresa = $this->empresaFiscalRepository->get();
            if ($empresa === null) {
                throw new NotaFiscalException('Empresa fiscal nao cadastrada em empresa_local.');
            }

            $config = [
                'atualizacao' => date('Y-m-d H:i:s'),
                'tpAmb' => (int)$empresa->ambiente_nfe,
                'razaosocial' => $empresa->nome,
                'siglaUF' => $empresa->uf,
                'cnpj' => $empresa->cnpj,
                'schemes' => 'PL_009_V4',
                'versao' => '4.00',
                'tokenIBPT' => '',
                'CSC' => '',
                'CSCid' => '',
            ];
            $configJson = json_encode($config, JSON_THROW_ON_ERROR);
            $conteudoCertificado = file_get_contents((string)$empresa->certificado_path);
            if ($conteudoCertificado === false) {
                throw new NotaFiscalException('Nao foi possivel ler o certificado digital informado.');
            }

            $tools = new Tools($configJson, Certificate::readPfx($conteudoCertificado, (string)$empresa->certificado_senha));
            $tools->model('55');
            $response = $tools->sefazCancela(
                $somenteDigitos((string)$notaPersistida['chave_acesso']),
                mb_substr($justificativa, 0, 255),
                $somenteDigitos((string)$notaPersistida['n_prot'])
            );

            $responseXml = simplexml_load_string($response);
            if (!$responseXml instanceof SimpleXMLElement) {
                throw new NotaFiscalException('Resposta invalida da SEFAZ.');
            }
            $retEnvEvento = $responseXml->xpath('//*[local-name()="retEnvEvento" or local-name()="retEvento"]');
            $retorno = $retEnvEvento[0] ?? $responseXml;
            $cStat = trim((string)(($retorno->xpath('./*[local-name()="cStat"]')[0] ?? null) ?: ''));
            $xMotivo = trim((string)(($retorno->xpath('./*[local-name()="xMotivo"]')[0] ?? null) ?: ''));
            if ($cStat !== '128') {
                throw new NotaFiscalException($xMotivo !== '' ? $xMotivo : 'Falha ao processar cancelamento na SEFAZ.');
            }

            $infEvento = $responseXml->xpath('//*[local-name()="retEvento"]/*[local-name()="infEvento"]');
            $infEventoNode = $infEvento[0] ?? null;
            $cStatEvento = trim((string)(($infEventoNode?->xpath('./*[local-name()="cStat"]')[0] ?? null) ?: ''));
            $xMotivoEvento = trim((string)(($infEventoNode?->xpath('./*[local-name()="xMotivo"]')[0] ?? null) ?: ''));
            if ($cStatEvento !== '135') {
                throw new NotaFiscalException($xMotivoEvento !== '' ? $xMotivoEvento : 'Cancelamento nao homologado pela SEFAZ.');
            }

            $this->pdo->beginTransaction();
            $stmtCancelaNota = $this->pdo->prepare(
                'UPDATE pedido_nfe SET status = :status, updated_at = NOW() WHERE id = :id'
            );
            $stmtCancelaNota->execute([':status' => NotaFiscal::STATUS_CANCELADA, ':id' => $nfeId]);

            if (!empty($notaPersistida['pedido_id'])) {
                $stmtPedido = $this->pdo->prepare(
                    'UPDATE pedidos SET nfe_emitida = FALSE, updated_at = NOW() WHERE id = :pedido_id'
                );
                $stmtPedido->execute([':pedido_id' => (string)$notaPersistida['pedido_id']]);
            }
            $this->pdo->commit();

            if (!empty($notaPersistida['servico_id'])) {
                (new AuditLogger($this->pdo))->registrar(
                    'fiscal',
                    'CANCELAR_SERVICO',
                    'nota_fiscal_produto',
                    null,
                    'NF-e de produto vinculada a servico cancelada.',
                    [
                        'servico_id' => (string)$notaPersistida['servico_id'],
                        'nota_fiscal_id' => $nfeId,
                    ]
                );
            }

            return ['sucesso' => true, 'erro' => null];
        } catch (CertificateException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->registrarLogErro($pedidoIdLog, $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Certificado digital invalido ou vencido'];
        } catch (NotaFiscalException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->registrarLogErro($pedidoIdLog, $e->getMessage());
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->registrarLogErro($pedidoIdLog, $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Falha na comunicacao com a SEFAZ'];
        }
    }

    /**
     * Lista pedidos faturados aptos a receber NF-e.
     *
     * @param array<string, mixed> $filters Filtros opcionais de data e cliente.
     * @return array<int, array<string, mixed>>
     */
    public function listarPedidosFaturaveis(array $filters): array
    {
        $conditionsPedido = [
            "p.status = 'FATURADO'",
            'COALESCE(p.nfe_emitida, FALSE) = FALSE',
        ];
        $params = [];

        if (!empty($filters['data_inicio'])) {
            $conditionsPedido[] = "(COALESCE(p.data_faturamento, p.data_pedido) AT TIME ZONE 'America/Sao_Paulo')::date >= :data_inicio";
            $params[':data_inicio'] = (string)$filters['data_inicio'];
        }

        if (!empty($filters['data_fim'])) {
            $conditionsPedido[] = "(COALESCE(p.data_faturamento, p.data_pedido) AT TIME ZONE 'America/Sao_Paulo')::date <= :data_fim";
            $params[':data_fim'] = (string)$filters['data_fim'];
        }

        if (!empty($filters['cliente_id'])) {
            $conditionsPedido[] = 'p.cliente_id::text = :cliente_id';
            $params[':cliente_id'] = (string)$filters['cliente_id'];
        }
        $conditionsServico = [
            "s.status = 'FATURADO'",
            'pn.id IS NULL',
        ];

        if (!empty($filters['data_inicio'])) {
            $conditionsServico[] = "(s.data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date >= :data_inicio";
        }

        if (!empty($filters['data_fim'])) {
            $conditionsServico[] = "(s.data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date <= :data_fim";
        }

        if (!empty($filters['cliente_id'])) {
            $conditionsServico[] = 's.cliente_id::text = :cliente_id';
        }

        $sql = "SELECT p.id,
                       'PEDIDO' AS origem_tipo,
                       p.id AS pedido_id,
                       NULL::uuid AS servico_id,
                       p.numero,
                       p.cliente_id,
                       COALESCE(p.data_faturamento, p.data_pedido) AS data_faturamento,
                       p.status,
                       p.valor_total,
                       p.nfe_emitida,
                       c.nome AS cliente_nome,
                       c.cpf_cnpj,
                       COUNT(pi.id) AS total_itens
                FROM pedidos p
                LEFT JOIN clientes c ON c.id::text = p.cliente_id::text
                LEFT JOIN pedido_itens pi ON pi.pedido_id::text = p.id::text
                WHERE " . implode(' AND ', $conditionsPedido) . "
                GROUP BY p.id, p.numero, p.cliente_id, p.data_faturamento, p.data_pedido, p.status, p.valor_total, p.nfe_emitida, c.nome, c.cpf_cnpj
                UNION ALL
                SELECT s.id,
                       'SERVICO' AS origem_tipo,
                       NULL::uuid AS pedido_id,
                       s.id AS servico_id,
                       s.numero,
                       s.cliente_id,
                       s.data_faturamento,
                       s.status,
                       s.valor_total,
                       FALSE AS nfe_emitida,
                       COALESCE(c.nome, s.nome_cliente) AS cliente_nome,
                       c.cpf_cnpj,
                       COUNT(si.id) + CASE WHEN COUNT(si.id) = 0 AND s.produto_id IS NOT NULL THEN 1 ELSE 0 END AS total_itens
                FROM servicos s
                LEFT JOIN clientes c ON c.id::text = s.cliente_id::text
                LEFT JOIN servico_itens si ON si.servico_id::text = s.id::text
                LEFT JOIN pedido_nfe pn
                    ON pn.servico_id = s.id
                   AND pn.status IN ('PENDENTE', 'EMITIDA', 'AUTORIZADA')
                WHERE " . implode(' AND ', $conditionsServico) . "
                GROUP BY s.id, s.numero, s.cliente_id, s.data_faturamento, s.status, s.valor_total, c.nome, s.nome_cliente, c.cpf_cnpj, s.produto_id
                HAVING COUNT(si.id) > 0 OR s.produto_id IS NOT NULL
                ORDER BY data_faturamento DESC, numero DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarServicoFiscal(string $servicoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.numero, s.data_faturamento, s.valor_total, s.observacoes, s.cliente_id, s.status,
                    s.nome_cliente,
                    c.nome AS cliente_nome,
                    c.cpf_cnpj,
                    c.logradouro,
                    c.numero_endereco,
                    c.complemento,
                    c.bairro,
                    c.cidade,
                    c.estado,
                    c.cep,
                    c.codigo_municipio,
                    c.ie,
                    c.ind_ie_dest,
                    s.produto_id,
                    s.produto_nome,
                    s.produto_quantidade,
                    s.produto_valor_unitario
             FROM servicos s
             LEFT JOIN clientes c ON c.id::text = s.cliente_id::text
             WHERE s.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $servicoId]);
        $servico = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$servico) {
            return null;
        }

        if (empty($servico['cliente_nome']) && !empty($servico['nome_cliente'])) {
            $servico['cliente_nome'] = $servico['nome_cliente'];
        }

        return $servico;
    }

    /**
     * @param array<string, mixed>|null $servico
     * @return array<int, array<string, mixed>>
     */
    private function buscarItensProdutoServico(string $servicoId, ?array $servico = null): array
    {
        $stmtItens = $this->pdo->prepare(
            "SELECT si.produto_id,
                    COALESCE(si.nome_produto, pr.nome, 'Produto sem nome') AS nome_produto,
                    si.quantidade,
                    si.valor_unitario,
                    si.valor_total_item,
                    COALESCE(pr.unidade, 'UN') AS unidade,
                    pf.ncm,
                    pf.cest,
                    pf.cfop,
                    pf.csosn_cst,
                    pf.cst_pis,
                    pf.cst_cofins,
                    COALESCE(pf.origem, '0') AS origem,
                    COALESCE(pf.aliquota_icms, 0) AS aliquota_icms,
                    COALESCE(pf.aliquota_ipi, 0) AS aliquota_ipi,
                    COALESCE(pf.aliquota_pis, 0) AS aliquota_pis,
                    COALESCE(pf.aliquota_cofins, 0) AS aliquota_cofins,
                    pf.codigo_beneficio_fiscal,
                    pf.ind_escala,
                    pf.cnpj_fabricante,
                    pf.modalidade_bc_icms,
                    COALESCE(pf.reducao_bc_icms, 0) AS reducao_bc_icms,
                    COALESCE(pf.aliquota_icms_st, 0) AS aliquota_icms_st
             FROM servico_itens si
             LEFT JOIN produtos pr ON pr.id = si.produto_id
             LEFT JOIN produto_fiscal pf ON pf.produto_id = si.produto_id
             WHERE si.servico_id = :servico_id
             ORDER BY si.created_at ASC"
        );
        $stmtItens->execute([':servico_id' => $servicoId]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($itens !== []) {
            return $itens;
        }

        $servico ??= $this->buscarServicoFiscal($servicoId);
        if ($servico === null || empty($servico['produto_id'])) {
            return [];
        }

        $stmtLegado = $this->pdo->prepare(
            "SELECT s.produto_id,
                    COALESCE(s.produto_nome, pr.nome, 'Produto sem nome') AS nome_produto,
                    COALESCE(NULLIF(s.produto_quantidade, 0), 1) AS quantidade,
                    COALESCE(s.produto_valor_unitario, 0) AS valor_unitario,
                    COALESCE(NULLIF(s.produto_quantidade, 0), 1) * COALESCE(s.produto_valor_unitario, 0) AS valor_total_item,
                    COALESCE(pr.unidade, 'UN') AS unidade,
                    pf.ncm,
                    pf.cest,
                    pf.cfop,
                    pf.csosn_cst,
                    pf.cst_pis,
                    pf.cst_cofins,
                    COALESCE(pf.origem, '0') AS origem,
                    COALESCE(pf.aliquota_icms, 0) AS aliquota_icms,
                    COALESCE(pf.aliquota_ipi, 0) AS aliquota_ipi,
                    COALESCE(pf.aliquota_pis, 0) AS aliquota_pis,
                    COALESCE(pf.aliquota_cofins, 0) AS aliquota_cofins,
                    pf.codigo_beneficio_fiscal,
                    pf.ind_escala,
                    pf.cnpj_fabricante,
                    pf.modalidade_bc_icms,
                    COALESCE(pf.reducao_bc_icms, 0) AS reducao_bc_icms,
                    COALESCE(pf.aliquota_icms_st, 0) AS aliquota_icms_st
             FROM servicos s
             LEFT JOIN produtos pr ON pr.id = s.produto_id
             LEFT JOIN produto_fiscal pf ON pf.produto_id = s.produto_id
             WHERE s.id = :servico_id
               AND s.produto_id IS NOT NULL
             LIMIT 1"
        );
        $stmtLegado->execute([':servico_id' => $servicoId]);
        $itemLegado = $stmtLegado->fetch(PDO::FETCH_ASSOC);

        return $itemLegado ? [$itemLegado] : [];
    }

    /**
     * Gera uma chave de acesso placeholder com 44 digitos para a NF-e.
     *
     * @param EmpresaFiscal $empresa Dados fiscais da empresa.
     * @param int $numeroNfe Numero sequencial da NF-e.
     */
    private function gerarChaveAcessoPlaceholder(EmpresaFiscal $empresa, int $numeroNfe): string
    {
        $ufMap = [
            'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15', 'AP' => '16', 'TO' => '17',
            'MA' => '21', 'PI' => '22', 'CE' => '23', 'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27', 'SE' => '28', 'BA' => '29',
            'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35',
            'PR' => '41', 'SC' => '42', 'RS' => '43',
            'MS' => '50', 'MT' => '51', 'GO' => '52', 'DF' => '53',
        ];

        $uf = strtoupper((string)($empresa->uf ?? ''));
        $cUF = $ufMap[$uf] ?? '00';
        $aamm = date('ym');
        $cnpj = str_pad(substr(preg_replace('/\D+/', '', (string)$empresa->cnpj) ?? '', 0, 14), 14, '0', STR_PAD_LEFT);
        $modelo = '55';
        $serie = str_pad(substr(preg_replace('/\D+/', '', (string)($empresa->serie_nfe ?? '1')) ?? '', 0, 3), 3, '0', STR_PAD_LEFT);
        $nNF = str_pad((string)$numeroNfe, 9, '0', STR_PAD_LEFT);
        $tpEmis = '1';
        $cNF = '00000000';
        $cDV = '0';

        return $cUF . $aamm . $cnpj . $modelo . $serie . $nNF . $tpEmis . $cNF . $cDV;
    }

    /**
     * Registra falhas de emissao em arquivo local.
     *
     * @param string $referencia Identificador sintetico da origem fiscal.
     * @param string $erro Mensagem de erro capturada.
     */
    private function registrarLogErro(string $referencia, string $erro): void
    {
        $diretorio = __DIR__ . '/../../../storage/logs';
        if (!is_dir($diretorio)) {
            @mkdir($diretorio, 0777, true);
        }

        $linha = sprintf("[%s] referencia=%s erro=%s%s", date('Y-m-d H:i:s'), $referencia, $erro, PHP_EOL);
        @file_put_contents($diretorio . '/fiscal.log', $linha, FILE_APPEND);
    }
}

