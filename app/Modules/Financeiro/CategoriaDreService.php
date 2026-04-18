<?php

namespace App\Modules\Financeiro;

class CategoriaDreService
{
    public function __construct(private readonly CategoriaDreRepository $repo) {}

    /**
     * @param string|string[]|null $tipo
     */
    public function listar(string|array|null $tipo = null, bool $apenasAtivas = false): array
    {
        return $this->repo->listar($tipo, $apenasAtivas);
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

        $tipo = trim((string) ($dados['tipo'] ?? 'Receita'));
        if ($tipo === 'Dedução') {
            $tipo = 'Deducao';
        }

        $cat            = new CategoriaDre();
        $cat->nome      = trim($dados['nome']);
        $cat->tipo      = $tipo !== '' ? $tipo : 'Receita';
        $cat->descricao = trim($dados['descricao'] ?? '') ?: null;
        $cat->ordem     = (int) ($dados['ordem'] ?? 0);
        $cat->ativo     = isset($dados['ativo']) && $dados['ativo'] === 'on';

        if ($id) {
            $cat->id = $id;
            $this->repo->atualizar($cat);
            return ['ok' => true, 'id' => $id];
        }

        $novoId = $this->repo->criar($cat);
        return ['ok' => true, 'id' => $novoId];
    }

    public function excluir(int $id): array
    {
        $ok = $this->repo->excluir($id);
        return ['ok' => $ok];
    }
}
