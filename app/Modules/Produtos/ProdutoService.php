<?php

namespace App\Modules\Produtos;

use PDO;
use Throwable;

/**
 * ProdutoService — orquestra casos de uso do módulo de Produtos.
 *
 * Responsabilidades:
 *   • Validar regras de negócio (NCM, CFOP, alíquotas, unicidade de código).
 *   • Coordenar ProdutoRepository e ProdutoFiscalRepository dentro de uma transação.
 *   • Calcular paginação.
 *
 * Não acessa banco diretamente — usa os repositories.
 * O PDO é injetado apenas para controle de transação (BEGIN / COMMIT / ROLLBACK).
 */
class ProdutoService
{
    public function __construct(
        private PDO                     $pdo,
        private ProdutoRepository       $repo,
        private ProdutoFiscalRepository $repoFiscal
    ) {}

    // ----------------------------------------------------------------
    // Consultas
    // ----------------------------------------------------------------

    /**
     * @return array{produtos: array[], total: int, totalPags: int, pagina: int}
     */
    public function listar(string $busca, int $pagina, int $porPagina): array
    {
        $total     = $this->repo->totalRegistros($busca);
        $totalPags = max(1, (int)ceil($total / $porPagina));
        $pagina    = min(max(1, $pagina), $totalPags);

        return [
            'produtos'  => $this->repo->listar($busca, $pagina, $porPagina),
            'total'     => $total,
            'totalPags' => $totalPags,
            'pagina'    => $pagina,
        ];
    }

    /**
     * Retorna produto + dados fiscais para preenchimento do formulário.
     *
     * @return array{produto: Produto, fiscal: ?ProdutoFiscal}|null
     */
    public function buscarParaEdicao(int $id): ?array
    {
        $produto = $this->repo->buscarPorId($id);
        if (!$produto) {
            return null;
        }

        return [
            'produto' => $produto,
            'fiscal'  => $this->repoFiscal->buscarPorProduto($id),
        ];
    }

    // ----------------------------------------------------------------
    // Persistência com transação
    // ----------------------------------------------------------------

    /**
     * Cria ou atualiza Produto e ProdutoFiscal dentro de uma única transação.
     *
     * @param  array    $dadosProduto  Campos do produto (strings/floats sanitizados).
     * @param  array    $dadosFiscais  Campos fiscais; se NCM vazio → fiscal não é gravado.
     * @param  int|null $id            Null = criação, int = atualização.
     * @return array{ok: bool, id: int, erros: string[]}
     */
    public function salvar(array $dadosProduto, array $dadosFiscais, ?int $id): array
    {
        $gerarSku = $id === null && empty(trim($dadosProduto['codigo'] ?? ''));

        $erros = [
            ...$this->validarProduto($dadosProduto, $id),
            ...$this->validarFiscais($dadosFiscais),
        ];

        if (!empty($erros)) {
            return ['ok' => false, 'id' => $id ?? 0, 'erros' => $erros];
        }

        $this->pdo->beginTransaction();

        try {
            if ($gerarSku) {
                $dadosProduto['codigo'] = $this->repo->getNextSku();
            }

            if ($id !== null) {
                $produtoAtual = $this->repo->buscarPorId($id);
                if ($produtoAtual === null) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'id' => $id, 'erros' => ['Produto não encontrado.']];
                }

                // Estoque atual só pode ser definido na criação; movimentações futuras
                // devem passar pelo inventário para manter histórico auditável.
                $dadosProduto['estoque_atual'] = $produtoAtual->estoque_atual;
            }

            $produto = Produto::fromArray($dadosProduto + ['id' => $id ?? 0]);

            if ($id) {
                $this->repo->atualizar($produto);
                $newId = $id;
            } else {
                $newId = $this->repo->criar($produto);
            }

            $fiscal = ProdutoFiscal::fromArray($dadosFiscais + ['produto_id' => $newId]);

            if (!$fiscal->vazio()) {
                $fiscal->produto_id = $newId;
                $this->repoFiscal->salvar($fiscal);
            } else {
                $this->repoFiscal->excluirPorProduto($newId);
            }

            $this->pdo->commit();

            return ['ok' => true, 'id' => $newId, 'erros' => []];

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log("ProdutoService::salvar — " . $e->getMessage());

            return ['ok' => false, 'id' => $id ?? 0, 'erros' => ['Erro interno ao salvar produto.']];
        }
    }

    public function excluir(int $id): bool
    {
        return $this->repo->excluir($id);
    }

    // ----------------------------------------------------------------
    // Validações de negócio
    // ----------------------------------------------------------------

    /** @return string[] */
    private function validarProduto(array $d, ?int $id): array
    {
        $erros = [];

        if (empty(trim($d['nome'] ?? ''))) {
            $erros[] = 'O campo <strong>Nome</strong> é obrigatório.';
        }

        if (empty($d['grupo_id'])) {
            $erros[] = 'Selecione um <strong>Grupo</strong>.';
        }

        if (empty($d['subgrupo_id'])) {
            $erros[] = 'Selecione um <strong>Subgrupo</strong>.';
        }

        if (!in_array($d['unidade'] ?? '', Produto::UNIDADES, true)) {
            $erros[] = 'Unidade de medida inválida.';
        }

        if ((float)($d['preco_venda'] ?? 0) < 0) {
            $erros[] = 'O <strong>Preço de Venda</strong> não pode ser negativo.';
        }

        if ((float)($d['preco_custo'] ?? 0) < 0) {
            $erros[] = 'O <strong>Preço de Custo</strong> não pode ser negativo.';
        }

        if (!empty($d['codigo'])) {
            $sku = trim($d['codigo']);
            if ($this->repo->existsBySku($sku, $id ?? 0)) {
                $erros[] = 'O <strong>Código (SKU)</strong> informado já está em uso por outro produto.';
            }
        }

        return $erros;
    }

    /**
     * Valida campos fiscais quando NCM for fornecido.
     *
     * @return string[]
     */
    private function validarFiscais(array $f): array
    {
        $ncm  = preg_replace('/\D/', '', $f['ncm']  ?? '');
        $cfop = preg_replace('/\D/', '', $f['cfop'] ?? '');
        $cest = preg_replace('/\D/', '', $f['cest'] ?? '');

        if ($ncm === '') {
            $temOutrosCampos = $cfop !== ''
                || $cest !== ''
                || !empty(trim($f['csosn_cst'] ?? ''))
                || !empty(trim($f['cst_pis'] ?? ''))
                || !empty(trim($f['cst_cofins'] ?? ''))
                || !empty(trim($f['modalidade_bc_icms'] ?? ''));
            if ($temOutrosCampos) {
                return ['O <strong>NCM</strong> é obrigatório para salvar dados fiscais.'];
            }
            return [];
        }

        $erros = [];

        if (strlen($ncm) !== 8) {
            $erros[] = 'O <strong>NCM</strong> deve ter exatamente 8 dígitos numéricos.';
        }

        $cfop = preg_replace('/\D/', '', $f['cfop'] ?? '');
        if ($cfop === '') {
            $erros[] = 'O <strong>CFOP</strong> é obrigatório quando NCM é informado.';
        } elseif (strlen($cfop) !== 4) {
            $erros[] = 'O <strong>CFOP</strong> deve ter exatamente 4 dígitos.';
        }

        $cest = preg_replace('/\D/', '', $f['cest'] ?? '');
        if ($cest !== '' && strlen($cest) !== 7) {
            $erros[] = 'O <strong>CEST</strong> deve ter exatamente 7 dígitos.';
        }

        if (empty(trim((string)($f['cst_pis'] ?? '')))) {
            $erros[] = 'O <strong>CST PIS</strong> é obrigatório quando NCM é informado.';
        } elseif (!array_key_exists((string)$f['cst_pis'], ProdutoFiscal::CST_PIS_COFINS)) {
            $erros[] = 'O <strong>CST PIS</strong> informado é inválido.';
        }

        if (empty(trim((string)($f['cst_cofins'] ?? '')))) {
            $erros[] = 'O <strong>CST COFINS</strong> é obrigatório quando NCM é informado.';
        } elseif (!array_key_exists((string)$f['cst_cofins'], ProdutoFiscal::CST_PIS_COFINS)) {
            $erros[] = 'O <strong>CST COFINS</strong> informado é inválido.';
        }

        if (empty(trim((string)($f['modalidade_bc_icms'] ?? '')))) {
            $erros[] = 'A <strong>Modalidade BC ICMS</strong> é obrigatória quando NCM é informado.';
        } elseif (!array_key_exists((string)$f['modalidade_bc_icms'], ProdutoFiscal::MODALIDADES_BC_ICMS)) {
            $erros[] = 'A <strong>Modalidade BC ICMS</strong> informada é inválida.';
        }

        if (!empty($f['codigo_beneficio_fiscal']) && mb_strlen(trim((string)$f['codigo_beneficio_fiscal'])) > 10) {
            $erros[] = 'O <strong>Código de Benefício Fiscal</strong> deve ter no máximo 10 caracteres.';
        }

        if (!empty($f['cnpj_fabricante'])) {
            $cnpjFabricante = preg_replace('/\D/', '', (string)$f['cnpj_fabricante']);
            if ($cnpjFabricante !== '' && strlen($cnpjFabricante) !== 14) {
                $erros[] = 'O <strong>CNPJ do Fabricante</strong> deve ter 14 dígitos.';
            }
        }

        if (!empty($f['ind_escala']) && !array_key_exists((string)$f['ind_escala'], ProdutoFiscal::IND_ESCALA)) {
            $erros[] = 'O <strong>Indicador de Escala</strong> informado é inválido.';
        }

        foreach (['aliquota_icms', 'aliquota_icms_st', 'aliquota_ipi', 'aliquota_pis', 'aliquota_cofins', 'reducao_bc_icms'] as $campo) {
            $valor = (float)str_replace(',', '.', (string)($f[$campo] ?? 0));
            if ($valor < 0) {
                $label = strtoupper(str_replace(['aliquota_', '_'], ['', ' '], $campo));
                $erros[] = "A alíquota de <strong>{$label}</strong> não pode ser negativa.";
            }
            if ($valor > 100) {
                $label = strtoupper(str_replace(['aliquota_', '_'], ['', ' '], $campo));
                $erros[] = "O campo <strong>{$label}</strong> não pode ser maior que 100.";
            }
        }

        return $erros;
    }
}
