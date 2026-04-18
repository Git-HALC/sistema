<?php

namespace App\Modules\Servicos;

use PDO;

class ServicoCatalogoService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ServicoCatalogoRepository $repo
    ) {}

    public function listar(string $busca, ?bool $ativo, int $pagina, int $porPagina): array
    {
        return $this->repo->listar($busca, $ativo, $pagina, $porPagina);
    }

    public function buscar(int $id): ?ServicoCatalogo
    {
        return $this->repo->findById($id);
    }

    /**
     * @return array{ok: bool, erros: string[], id: ?int}
     */
    public function salvar(array $dados, ?int $id = null): array
    {
        $erros = $this->validar($dados, $id);
        if ($erros) {
            return ['ok' => false, 'erros' => $erros, 'id' => $id];
        }

        $payload = [
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] !== '' ? $dados['descricao'] : null,
            'valor_base' => $dados['valor_base'],
            'ativo' => $dados['ativo'],
        ];

        if ($id === null) {
            $novoId = $this->repo->create($payload);
            return ['ok' => true, 'erros' => [], 'id' => $novoId];
        }

        $this->repo->update($id, $payload);
        return ['ok' => true, 'erros' => [], 'id' => $id];
    }

    public function inativar(int $id): bool
    {
        return $this->repo->setAtivo($id, false);
    }

    public function reativar(int $id): bool
    {
        return $this->repo->setAtivo($id, true);
    }

    /**
     * @return string[]
     */
    private function validar(array $dados, ?int $id): array
    {
        $erros = [];
        $nome = trim((string)($dados['nome'] ?? ''));
        if ($nome === '') {
            $erros[] = 'Nome é obrigatório.';
        } elseif (mb_strlen($nome) > 255) {
            $erros[] = 'Nome não pode ter mais de 255 caracteres.';
        } elseif ($this->repo->existsByNome($nome, $id)) {
            $erros[] = 'Já existe um serviço com este nome.';
        }

        $valor = (float)($dados['valor_base'] ?? 0);
        if ($valor < 0) {
            $erros[] = 'Valor base não pode ser negativo.';
        }

        return $erros;
    }
}
