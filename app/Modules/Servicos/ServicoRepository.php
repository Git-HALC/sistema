<?php

namespace App\Modules\Servicos;

use PDO;
use RuntimeException;

class ServicoRepository
{
    private const TABLE = 'servicos';

    private ?bool $produtoQuantidadeColumnExists = null;
    private ?bool $servicoValorColumnExists = null;
    private ?bool $produtoValorUnitarioColumnExists = null;
    private ?bool $descontoColumnsExist = null;

    public function __construct(private readonly PDO $pdo) {}

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function create(array $data): Servico
    {
        $providedId = trim((string)($data['id'] ?? ''));
        $id = $providedId !== '' ? $providedId : self::uuid();
        $payload = $data;
        $payload['id'] = $id;

        $columns = [
            'id', 'cliente_id', 'usuario_id', 'orcamento_id', 'servico_catalogo_id', 'produto_id',
            'data_servico', 'status', 'nome_cliente', 'telefone_cliente', 'servico_nome', 'produto_nome',
            'placa', 'modelo_veiculo', 'valor_total', 'observacoes', 'data_faturamento', 'ativo',
        ];
        $placeholders = [
            ':id', ':cliente_id', ':usuario_id', ':orcamento_id', ':servico_catalogo_id', ':produto_id',
            ':data_servico', ':status', ':nome_cliente', ':telefone_cliente', ':servico_nome', ':produto_nome',
            ':placa', ':modelo_veiculo', ':valor_total', ':observacoes', ':data_faturamento', ':ativo',
        ];

        if ($this->hasProdutoQuantidadeColumn()) {
            array_splice($columns, 6, 0, 'produto_quantidade');
            array_splice($placeholders, 6, 0, ':produto_quantidade');
        }

        if ($this->hasServicoValorColumn()) {
            array_splice($columns, 7, 0, 'servico_valor');
            array_splice($placeholders, 7, 0, ':servico_valor');
        }

        if ($this->hasProdutoValorUnitarioColumn()) {
            array_splice($columns, 8, 0, 'produto_valor_unitario');
            array_splice($placeholders, 8, 0, ':produto_valor_unitario');
        }

        if ($this->hasDescontoColumns()) {
            array_splice($columns, 17, 0, ['desconto_tipo', 'desconto_valor']);
            array_splice($placeholders, 17, 0, [':desconto_tipo', ':desconto_valor']);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($this->bind($payload));

        $servico = $this->findById($id);
        if ($servico === null) {
            throw new RuntimeException('Falha ao criar servico.');
        }

        return $servico;
    }

    public function update(string $id, array $data): Servico
    {
        $atual = $this->findById($id);
        if ($atual === null) {
            throw new RuntimeException('Servico nao encontrado para atualizacao.');
        }

        $payload = array_merge($atual->toArray(), $data, ['id' => $id]);

        $sets = [
            'cliente_id = :cliente_id',
            'usuario_id = :usuario_id',
            'orcamento_id = :orcamento_id',
            'servico_catalogo_id = :servico_catalogo_id',
            'produto_id = :produto_id',
            'data_servico = :data_servico',
            'status = :status',
            'nome_cliente = :nome_cliente',
            'telefone_cliente = :telefone_cliente',
            'servico_nome = :servico_nome',
            'produto_nome = :produto_nome',
            'placa = :placa',
            'modelo_veiculo = :modelo_veiculo',
            'valor_total = :valor_total',
            'observacoes = :observacoes',
            'data_faturamento = :data_faturamento',
            'ativo = :ativo',
            'updated_at = NOW()',
        ];

        if ($this->hasProdutoQuantidadeColumn()) {
            array_splice($sets, 5, 0, 'produto_quantidade = :produto_quantidade');
        }

        if ($this->hasServicoValorColumn()) {
            array_splice($sets, 6, 0, 'servico_valor = :servico_valor');
        }

        if ($this->hasProdutoValorUnitarioColumn()) {
            array_splice($sets, 7, 0, 'produto_valor_unitario = :produto_valor_unitario');
        }

        if ($this->hasDescontoColumns()) {
            array_splice($sets, 16, 0, ['desconto_tipo = :desconto_tipo', 'desconto_valor = :desconto_valor']);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($this->bind($payload));

        return $this->findById($id) ?? $atual;
    }

    public function findById(string $id): ?Servico
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Servico::fromArray($row) : null;
    }

    public function findByOrcamentoId(int $orcamentoId): ?Servico
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE orcamento_id = :orcamento_id AND ativo = TRUE ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([':orcamento_id' => $orcamentoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Servico::fromArray($row) : null;
    }

    public function findAll(array $filters = [], int $perPage = 15): array
    {
        $pagina = max(1, (int)($filters['page'] ?? $filters['pagina'] ?? 1));
        $offset = ($pagina - 1) * $perPage;
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' ' . $where . ' ORDER BY data_servico DESC, created_at DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dados = array_map(
            static fn(array $row): array => Servico::fromArray($row)->toArray(),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' ' . $where);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $pagina,
            'itens_por_pagina' => $perPage,
            'total_paginas' => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    public function atualizarStatusSimples(string $id, string $novoStatus): bool
    {
        $stmt = $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET status = :status, updated_at = NOW() WHERE id = :id AND ativo = TRUE');
        $stmt->execute([
            ':id' => $id,
            ':status' => strtoupper($novoStatus),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function buildWhere(array $filters): array
    {
        $conds = [];
        $params = [];
        $kanbanMode = !empty($filters['kanban_mode']);

        if (isset($filters['ativo'])) {
            $conds[] = 'ativo = :ativo';
            $params[':ativo'] = (bool)$filters['ativo'];
        } else {
            $conds[] = 'ativo = TRUE';
        }

        if (!empty($filters['status'])) {
            $conds[] = 'status = :status';
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['cliente_id'])) {
            $conds[] = 'cliente_id = :cliente_id';
            $params[':cliente_id'] = (int)$filters['cliente_id'];
        }

        if (!empty($filters['data_inicio'])) {
            $conds[] = "(data_servico AT TIME ZONE 'America/Sao_Paulo')::date >= :data_inicio";
            $params[':data_inicio'] = (string)$filters['data_inicio'];
        }

        if (!empty($filters['data_fim']) && !$kanbanMode) {
            $conds[] = "(data_servico AT TIME ZONE 'America/Sao_Paulo')::date <= :data_fim";
            $params[':data_fim'] = (string)$filters['data_fim'];
        }

        if (!empty($filters['busca'])) {
            $conds[] = "(CAST(numero AS TEXT) ILIKE :busca OR nome_cliente ILIKE :busca OR servico_nome ILIKE :busca OR COALESCE(produto_nome, '') ILIKE :busca OR COALESCE(placa, '') ILIKE :busca)";
            $params[':busca'] = '%' . (string)$filters['busca'] . '%';
        }

        return [$conds ? 'WHERE ' . implode(' AND ', $conds) : '', $params];
    }

    private function bind(array $data): array
    {
        $id = trim((string)($data['id'] ?? ''));
        if ($id === '') {
            $id = self::uuid();
        }

        $params = [
            ':id' => $id,
            ':cliente_id' => $this->intOrNull($data['cliente_id'] ?? null),
            ':usuario_id' => $this->intOrNull($data['usuario_id'] ?? null),
            ':orcamento_id' => $this->intOrNull($data['orcamento_id'] ?? null),
            ':servico_catalogo_id' => $this->intOrNull($data['servico_catalogo_id'] ?? null),
            ':produto_id' => $this->intOrNull($data['produto_id'] ?? null),
            ':data_servico' => $data['data_servico'] ?? date('Y-m-d H:i:s'),
            ':status' => strtoupper((string)($data['status'] ?? Servico::STATUS_PENDENTE)),
            ':nome_cliente' => (string)($data['nome_cliente'] ?? ''),
            ':telefone_cliente' => $data['telefone_cliente'] ?? null,
            ':servico_nome' => (string)($data['servico_nome'] ?? ''),
            ':produto_nome' => $data['produto_nome'] ?? null,
            ':placa' => $data['placa'] ?? null,
            ':modelo_veiculo' => $data['modelo_veiculo'] ?? null,
            ':valor_total' => (float)($data['valor_total'] ?? 0),
            ':observacoes' => $data['observacoes'] ?? null,
            ':data_faturamento' => $data['data_faturamento'] ?? null,
            ':ativo' => filter_var($data['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        ];

        if ($this->hasProdutoQuantidadeColumn()) {
            $params[':produto_quantidade'] = (float)($data['produto_quantidade'] ?? 0);
        }

        if ($this->hasServicoValorColumn()) {
            $params[':servico_valor'] = (float)($data['servico_valor'] ?? 0);
        }

        if ($this->hasProdutoValorUnitarioColumn()) {
            $params[':produto_valor_unitario'] = (float)($data['produto_valor_unitario'] ?? 0);
        }

        if ($this->hasDescontoColumns()) {
            $params[':desconto_tipo'] = $this->descontoTipoOrNull($data['desconto_tipo'] ?? null);
            $params[':desconto_valor'] = round((float)($data['desconto_valor'] ?? 0), 2);
        }

        return $params;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    private function hasProdutoQuantidadeColumn(): bool
    {
        if ($this->produtoQuantidadeColumnExists !== null) {
            return $this->produtoQuantidadeColumnExists;
        }

        $stmt = $this->pdo->prepare("\n            SELECT 1\n            FROM information_schema.columns\n            WHERE table_schema = 'public'\n              AND table_name = 'servicos'\n              AND column_name = 'produto_quantidade'\n            LIMIT 1\n        ");
        $stmt->execute();

        return $this->produtoQuantidadeColumnExists = (bool)$stmt->fetchColumn();
    }

    private function hasServicoValorColumn(): bool
    {
        if ($this->servicoValorColumnExists !== null) {
            return $this->servicoValorColumnExists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'servicos'
              AND column_name = 'servico_valor'
            LIMIT 1
        ");
        $stmt->execute();

        return $this->servicoValorColumnExists = (bool)$stmt->fetchColumn();
    }

    private function hasProdutoValorUnitarioColumn(): bool
    {
        if ($this->produtoValorUnitarioColumnExists !== null) {
            return $this->produtoValorUnitarioColumnExists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'servicos'
              AND column_name = 'produto_valor_unitario'
            LIMIT 1
        ");
        $stmt->execute();

        return $this->produtoValorUnitarioColumnExists = (bool)$stmt->fetchColumn();
    }

    private function hasDescontoColumns(): bool
    {
        if ($this->descontoColumnsExist !== null) {
            return $this->descontoColumnsExist;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'servicos'
              AND column_name IN ('desconto_tipo', 'desconto_valor')
        ");
        $stmt->execute();

        return $this->descontoColumnsExist = (int)$stmt->fetchColumn() === 2;
    }

    private function descontoTipoOrNull(mixed $value): ?string
    {
        $tipo = strtoupper(trim((string)$value));
        if ($tipo === '') {
            return null;
        }

        return in_array($tipo, ['VALOR', 'PERCENTUAL'], true) ? $tipo : null;
    }
}
