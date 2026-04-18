<?php

namespace App\Modules\Financeiro;

class FormaPagamentoService
{
    public function __construct(private readonly FormaPagamentoRepository $repo) {}

    public function listar(bool $apenasAtivas = false): array
    {
        return $this->repo->listar($apenasAtivas);
    }

    public function salvar(array $dados, ?int $id = null): array
    {
        $erros = [];
        $tipo = strtoupper(trim((string)($dados['tipo'] ?? '')));
        $taxa = isset($dados['taxa']) ? (float) str_replace(',', '.', (string) $dados['taxa']) : 0.0;
        $prazoDias = isset($dados['prazo_dias']) ? (int) $dados['prazo_dias'] : 0;
        $adquirenteId = !empty($dados['adquirente_id']) ? (int) $dados['adquirente_id'] : null;
        $contaId = !empty($dados['conta_id']) ? (int) $dados['conta_id'] : null;

        if (empty(trim((string)($dados['nome'] ?? '')))) {
            $erros[] = 'Nome e obrigatorio.';
        }

        if (!in_array($tipo, [
            FormaPagamento::TIPO_DINHEIRO,
            FormaPagamento::TIPO_PIX,
            FormaPagamento::TIPO_TRANSFERENCIA,
            FormaPagamento::TIPO_CARTAO_CREDITO,
            FormaPagamento::TIPO_CARTAO_DEBITO,
            FormaPagamento::TIPO_BOLETO,
            FormaPagamento::TIPO_A_FATURAR,
        ], true)) {
            $erros[] = 'Tipo de forma de pagamento invalido.';
        }

        if ($taxa < 0) {
            $erros[] = 'A taxa nao pode ser negativa.';
        }

        if ($prazoDias < 0) {
            $erros[] = 'O prazo de recebimento nao pode ser negativo.';
        }

        if ($tipo === FormaPagamento::TIPO_DINHEIRO) {
            $taxa = 0.0;
            $prazoDias = 0;
            $adquirenteId = null;
            if ($contaId === null) {
                $erros[] = 'Selecione o banco que recebe para pagamentos em dinheiro.';
            }
        }

        if (in_array($tipo, [FormaPagamento::TIPO_PIX, FormaPagamento::TIPO_TRANSFERENCIA, FormaPagamento::TIPO_BOLETO], true)) {
            $adquirenteId = null;
            $prazoDias = 0;
            if ($contaId === null) {
                $erros[] = 'Selecione o banco que recebe para esta forma de pagamento.';
            }
        }

        if (in_array($tipo, [FormaPagamento::TIPO_CARTAO_CREDITO, FormaPagamento::TIPO_CARTAO_DEBITO], true)) {
            if ($adquirenteId === null) {
                $erros[] = 'Selecione a adquirente do cartao.';
            }
            if ($contaId === null) {
                $erros[] = 'Selecione o banco que recebe para o cartao.';
            }
        }

        if ($tipo === FormaPagamento::TIPO_A_FATURAR) {
            $taxa = 0.0;
            $prazoDias = 0;
            $adquirenteId = null;
            $contaId = null;
        }

        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        $fp = new FormaPagamento();
        $fp->nome = trim((string)$dados['nome']);
        $fp->tipo = $tipo;
        $fp->descricao = trim((string)($dados['descricao'] ?? '')) ?: null;
        $fp->adquirenteId = $adquirenteId;
        $fp->taxa = $taxa;
        $fp->prazoDias = $prazoDias;
        $fp->contaId = $contaId;
        $fp->ativo = (bool)($dados['ativo'] ?? true);

        if ($id) {
            $fp->id = $id;
            $this->repo->atualizar($fp);
            return ['ok' => true, 'id' => $id];
        }

        $novoId = $this->repo->criar($fp);
        return ['ok' => true, 'id' => $novoId];
    }

    public function excluir(int $id): array
    {
        $ok = $this->repo->excluir($id);
        return ['ok' => $ok];
    }
}
