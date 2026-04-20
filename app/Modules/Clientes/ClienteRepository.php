<?php

namespace App\Modules\Clientes;

use PDO;

/**
 * ClienteRepository — acesso à tabela `clientes`.
 *
 * Responsabilidades: SQL puro, sem validação de negócio.
 */
class ClienteRepository
{
    private const TABLE = 'clientes';
    /** @var array<string, bool>|null */
    private ?array $availableColumns = null;

    public function __construct(private readonly PDO $pdo) {}

    // =========================================================================
    // Leitura
    // =========================================================================

    public function listar(int $pagina, int $porPagina): array
    {
        $offset = max(0, ($pagina - 1) * $porPagina);
        $cols = $this->availableColumns();
        $select = ['id', 'nome', 'cpf_cnpj', 'email', 'telefone'];
        foreach (['eh_cliente', 'eh_fornecedor'] as $col) {
            if (isset($cols[$col])) {
                $select[] = $col;
            }
        }
        $select = array_merge($select, ['cidade', 'estado', 'codigo_municipio', 'ie', 'ind_ie_dest', 'prazo_faturamento_dias', 'ativo', 'created_at', 'updated_at']);

        $stmt = $this->pdo->prepare("
            SELECT " . implode(', ', $select) . "
            FROM " . self::TABLE . "
            WHERE ativo = TRUE
            ORDER BY nome ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalRegistros(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM " . self::TABLE . " WHERE ativo = TRUE"
        )->fetchColumn();
    }

    public function listarFornecedoresAtivos(): array
    {
        $cols = $this->availableColumns();
        $whereFornecedor = isset($cols['eh_fornecedor'])
            ? 'COALESCE(eh_fornecedor, FALSE) = TRUE'
            : 'FALSE';

        $stmt = $this->pdo->query("
            SELECT id, nome
            FROM " . self::TABLE . "
            WHERE ativo = TRUE
              AND {$whereFornecedor}
            ORDER BY nome ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?Cliente
    {
        $cols = $this->availableColumns();
        $select = [
            'id',
            'nome',
            'cpf_cnpj',
            'email',
            'telefone',
            'cidade',
            'estado',
            'ativo',
            'created_at',
            'updated_at',
        ];
        foreach (['eh_cliente', 'eh_fornecedor'] as $col) {
            if (isset($cols[$col])) {
                $select[] = $col;
            }
        }
        foreach (['logradouro', 'numero_endereco', 'complemento', 'bairro', 'cep', 'codigo_municipio', 'ie', 'ind_ie_dest', 'prazo_faturamento_dias'] as $col) {
            if (isset($cols[$col])) {
                $select[] = $col;
            }
        }

        $stmt = $this->pdo->prepare(
            "SELECT " . implode(', ', $select) . " 
             FROM " . self::TABLE . " 
             WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? Cliente::fromArray($row) : null;
    }

    public function emailExiste(string $email, int $ignorarId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM " . self::TABLE . " WHERE LOWER(email) = LOWER(:email) AND id <> :id LIMIT 1"
        );
        $stmt->execute([':email' => $email, ':id' => $ignorarId]);
        return (bool) $stmt->fetch();
    }

    public function cpfCnpjExiste(string $cpfCnpj, int $ignorarId = 0): bool
    {
        $valor = preg_replace('/\D/', '', $cpfCnpj);
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM " . self::TABLE . " 
             WHERE REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', '') = :valor 
             AND id <> :id LIMIT 1"
        );
        $stmt->execute([':valor' => $valor, ':id' => $ignorarId]);
        return (bool) $stmt->fetch();
    }

    // =========================================================================
    // Escrita
    // =========================================================================

    /**
     * @param array<string, mixed> $dados
     */
    public function criar(array $dados): int
    {
        $cols = $this->availableColumns();
        $campos = ['nome', 'cpf_cnpj', 'email', 'telefone', 'cidade', 'estado'];
        foreach (['eh_cliente', 'eh_fornecedor'] as $col) {
            if (isset($cols[$col])) {
                $campos[] = $col;
            }
        }
        foreach (['logradouro', 'numero_endereco', 'complemento', 'bairro', 'cep', 'codigo_municipio', 'ie', 'ind_ie_dest', 'prazo_faturamento_dias'] as $col) {
            if (isset($cols[$col])) {
                $campos[] = $col;
            }
        }
        $campos[] = 'ativo';

        $placeholders = array_map(
            static fn (string $campo): string => $campo === 'ativo' ? 'TRUE' : ':' . $campo,
            $campos
        );

        $payload = [
            ':nome' => $dados['nome'],
            ':cpf_cnpj' => preg_replace('/\D/', '', (string)$dados['cpf_cnpj']),
            ':email' => $dados['email'],
            ':telefone' => $dados['telefone'],
            ':cidade' => $dados['cidade'],
            ':estado' => $dados['estado'],
        ];
        foreach (['eh_cliente', 'eh_fornecedor'] as $col) {
            if (isset($cols[$col])) {
                $payload[':' . $col] = $this->boolToPg(!empty($dados[$col]));
            }
        }
        foreach (['logradouro', 'numero_endereco', 'complemento', 'bairro', 'cep', 'codigo_municipio', 'ie', 'ind_ie_dest', 'prazo_faturamento_dias'] as $col) {
            if (isset($cols[$col])) {
                $payload[':' . $col] = $dados[$col] ?? null;
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (" . implode(', ', $campos) . ")
            VALUES (" . implode(', ', $placeholders) . ")
            RETURNING id
        ");
        $stmt->execute($payload);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(int $id, array $dados): void
    {
        $cols = $this->availableColumns();
        $sets = [
            'nome = :nome',
            'cpf_cnpj = :cpf_cnpj',
            'email = :email',
            'telefone = :telefone',
            'cidade = :cidade',
            'estado = :estado',
        ];
        foreach (['eh_cliente', 'eh_fornecedor'] as $col) {
            if (isset($cols[$col])) {
                $sets[] = $col . ' = :' . $col;
            }
        }
        foreach (['logradouro', 'numero_endereco', 'complemento', 'bairro', 'cep', 'codigo_municipio', 'ie', 'ind_ie_dest', 'prazo_faturamento_dias'] as $col) {
            if (isset($cols[$col])) {
                $sets[] = $col . ' = :' . $col;
            }
        }
        $sets[] = 'updated_at = NOW()';

        $payload = [
            ':id' => $id,
            ':nome' => $dados['nome'],
            ':cpf_cnpj' => preg_replace('/\D/', '', (string)$dados['cpf_cnpj']),
            ':email' => $dados['email'],
            ':telefone' => $dados['telefone'],
            ':cidade' => $dados['cidade'],
            ':estado' => $dados['estado'],
        ];
        foreach (['eh_cliente', 'eh_fornecedor'] as $col) {
            if (isset($cols[$col])) {
                $payload[':' . $col] = $this->boolToPg(!empty($dados[$col]));
            }
        }
        foreach (['logradouro', 'numero_endereco', 'complemento', 'bairro', 'cep', 'codigo_municipio', 'ie', 'ind_ie_dest', 'prazo_faturamento_dias'] as $col) {
            if (isset($cols[$col])) {
                $payload[':' . $col] = $dados[$col] ?? null;
            }
        }

        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE . "
            SET " . implode(",\n                ", $sets) . "
            WHERE id = :id
        ");
        $stmt->execute($payload);
    }

    public function buscarPorTermo(string $termo, int $limite, int $pagina): array
    {
        $offset = max(0, ($pagina - 1) * $limite);
        $busca  = '%' . str_replace('%', '\\%', $termo) . '%';
        $cols = $this->availableColumns();
        $select = ['id', 'nome', 'cpf_cnpj', 'email', 'telefone'];
        foreach (['eh_cliente', 'eh_fornecedor'] as $col) {
            if (isset($cols[$col])) {
                $select[] = $col;
            }
        }
        $select = array_merge($select, ['cidade', 'estado', 'codigo_municipio', 'ie', 'ind_ie_dest', 'prazo_faturamento_dias', 'ativo', 'created_at', 'updated_at']);

        $stmt = $this->pdo->prepare("
            SELECT " . implode(', ', $select) . "
            FROM " . self::TABLE . "
            WHERE ativo = TRUE 
              AND (nome ILIKE :termo OR cpf_cnpj LIKE :cpf_termo OR email ILIKE :termo)
            ORDER BY nome ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':termo', $busca);
        $stmt->bindValue(':cpf_termo', preg_replace('/\D/', '', $termo) . '%');
        $stmt->bindValue(':limit',  $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function excluir(int $id): bool
    {
        return $this->pdo
            ->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id")
            ->execute([':id' => $id]);
    }

    /**
     * @return array<string, bool>
     */
    private function availableColumns(): array
    {
        if ($this->availableColumns !== null) {
            return $this->availableColumns;
        }

        $stmt = $this->pdo->prepare(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = :table"
        );
        $stmt->execute([':table' => self::TABLE]);

        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $col) {
            $cols[(string)$col] = true;
        }

        $this->availableColumns = $cols;
        return $cols;
    }

    private function boolToPg(bool $value): string
    {
        return $value ? 'TRUE' : 'FALSE';
    }
}
