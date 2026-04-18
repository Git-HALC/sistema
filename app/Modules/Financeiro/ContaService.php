<?php

namespace App\Modules\Financeiro;

class ContaService
{
    public function __construct(private readonly ContaRepository $repo) {}

    public function listar(bool $apenasAtivas = false): array
    {
        return $this->repo->listar($apenasAtivas);
    }

    public function salvar(array $dados, ?int $id = null): array
    {
        $erros = [];

        if (empty(trim($dados['nome'] ?? ''))) {
            $erros[] = 'Nome é obrigatório.';
        }

        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        $conta               = new Conta();
        $conta->nome         = trim($dados['nome']);
        $conta->tipo         = $dados['tipo'] ?? 'Banco';
        $conta->banco        = trim($dados['banco'] ?? '') ?: null;
        $conta->agencia      = trim($dados['agencia'] ?? '') ?: null;
        $conta->numeroConta  = trim($dados['numero_conta']   ?? '') ?: null;
        $conta->saldoInicial = (float) str_replace(',', '.', (string)($dados['saldo_inicial'] ?? 0));
        $conta->dataSaldoInicial = !empty($dados['data_saldo_inicial']) ? (string)$dados['data_saldo_inicial'] : date('Y-m-d');
        $conta->ativo        = (bool) ($dados['ativo'] ?? true);

        if ($id) {
            $conta->id = $id;
            $this->repo->atualizar($conta);
            return ['ok' => true, 'id' => $id];
        }

        $novoId = $this->repo->criar($conta);
        return ['ok' => true, 'id' => $novoId];
    }

    public function excluir(int $id): array
    {
        $ok = $this->repo->excluir($id);
        return ['ok' => $ok];
    }
}
