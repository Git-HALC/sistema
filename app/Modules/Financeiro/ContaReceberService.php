<?php

namespace App\Modules\Financeiro;

use RuntimeException;

class ContaReceberService
{
    public function __construct(private readonly ContaReceberRepository $repo) {}

    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        if (($filtros['status'] ?? null) === 'Vencido') {
            return $this->repo->buscarVencidos($filtros, $pagina, $porPagina);
        }

        return $this->repo->listar($filtros, $pagina, $porPagina);
    }

    public function criar(array $dados): array
    {
        $erros = $this->validarDados($dados);
        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        $id = $this->repo->criar($dados);
        return ['ok' => true, 'id' => $id];
    }

    public function atualizar(int $id, array $dados): array
    {
        $erros = $this->validarDados($dados);
        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        try {
            $this->repo->atualizar($id, $dados);
            return ['ok' => true];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'erros' => [$e->getMessage()]];
        }
    }

    public function baixar(int $id, array $dados): array
    {
        if (empty($dados['forma_pagamento_id'])) {
            return ['ok' => false, 'erros' => ['Selecione uma forma de pagamento.']];
        }

        if (empty($dados['categoria_dre_id'])) {
            return ['ok' => false, 'erros' => ['Selecione uma categoria DRE (Receita).']];
        }

        $valorRecebidoEfetivo = (float)($dados['valor_pago'] ?? $dados['valor_recebido'] ?? 0);
        $desconto = (float)($dados['desconto_recebimento'] ?? 0);

        if ($valorRecebidoEfetivo <= 0) {
            return ['ok' => false, 'erros' => ['Valor recebido deve ser maior que zero.']];
        }

        if (($valorRecebidoEfetivo + $desconto) < 0) {
            return ['ok' => false, 'erros' => ['Desconto nao pode gerar valor negativo.']];
        }

        try {
            $this->repo->baixar($id, $dados);
            return ['ok' => true];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'erros' => [$e->getMessage()]];
        }
    }

    public function estornar(int $id, ?float $valorEstorno = null, ?int $usuarioId = null): array
    {
        try {
            $this->repo->estornar($id, $valorEstorno, $usuarioId);
            return ['ok' => true];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'erros' => [$e->getMessage()]];
        }
    }

    public function excluir(int $id): array
    {
        try {
            $this->repo->excluir($id);
            return ['ok' => true];
        } catch (NfeVinculadaException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return ['ok' => false, 'erros' => [$e->getMessage()]];
        }
    }

    private function validarDados(array $dados): array
    {
        $erros = [];

        if (empty($dados['forma_pagamento_id'])) {
            $erros[] = 'Forma de pagamento e obrigatoria.';
        }

        if (empty($dados['valor']) || (float)$dados['valor'] <= 0) {
            $erros[] = 'Valor deve ser maior que zero.';
        }

        if (empty($dados['data_vencimento'])) {
            $erros[] = 'Data de vencimento e obrigatoria.';
        }

        return $erros;
    }
}
