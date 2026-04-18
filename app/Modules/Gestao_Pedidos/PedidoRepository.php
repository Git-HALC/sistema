<?php

namespace App\Modules\Gestao_Pedidos;

use PDO;
use RuntimeException;

class PedidoRepository
{
    private const TABLE = 'pedidos';
    private ?bool $hasUsuarioIdColumnCache = null;
    private ?bool $hasDescontoColumnsCache = null;

    public function __construct(private readonly PDO $pdo) {}

    public function create(array $data): Pedido
    {
        $id = $data['id'] ?? self::uuid();

        $columns = [
            'id', 'cliente_id', 'orcamento_id', 'data_pedido', 'status', 'valor_total', 'observacoes',
            'data_faturamento', 'data_entrega_prevista', 'data_entrega_realizada', 'ativo',
        ];

        $params = [
            ':id' => $id,
            ':cliente_id' => $this->intOrNull($data['cliente_id'] ?? null),
            ':orcamento_id' => $this->intOrNull($data['orcamento_id'] ?? null),
            ':data_pedido' => $data['data_pedido'] ?? date('Y-m-d H:i:s'),
            ':status' => strtoupper((string)($data['status'] ?? Pedido::STATUS_PENDENTE)),
            ':valor_total' => (float)($data['valor_total'] ?? 0),
            ':observacoes' => $data['observacoes'] ?? null,
            ':data_faturamento' => $this->dateTimeOrNull($data['data_faturamento'] ?? null),
            ':data_entrega_prevista' => $this->dateOrNull($data['data_entrega_prevista'] ?? null),
            ':data_entrega_realizada' => $this->dateOrNull($data['data_entrega_realizada'] ?? null),
            ':ativo' => $this->boolToPg(!array_key_exists('ativo', $data) || (bool)$data['ativo']),
        ];

        if ($this->hasUsuarioIdColumn()) {
            $columns[] = 'usuario_id';
            $params[':usuario_id'] = $this->intOrNull($data['usuario_id'] ?? null);
        }

        if ($this->hasDescontoColumns()) {
            $columns[] = 'desconto_tipo';
            $columns[] = 'desconto_valor';
            $params[':desconto_tipo'] = $this->descontoTipoOrNull($data['desconto_tipo'] ?? null);
            $params[':desconto_valor'] = round((float)($data['desconto_valor'] ?? 0), 2);
        }

        $placeholders = array_map(static fn(string $c): string => ':' . $c, $columns);
        $stmt = $this->pdo->prepare(
            "INSERT INTO " . self::TABLE . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ')'
        );

        $stmt->execute($params);

        $pedido = $this->findById((string)$id);
        if ($pedido === null) {
            throw new RuntimeException('Falha ao criar pedido.');
        }

        return $pedido;
    }

    public function findById(string $id, array $relations = []): ?Pedido
    {
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $pedido = Pedido::fromArray($row);

        if (in_array('cliente', $relations, true)) {
            $pedido->cliente = $this->buscarCliente($pedido->cliente_id);
        }
        if (in_array('orcamento', $relations, true)) {
            $pedido->orcamento = $this->buscarOrcamento($pedido->orcamento_id);
        }

        return $pedido;
    }

    /**
     * Busca paginada de pedidos com filtros
     * 
     * OTIMIZA??O: Esta query usa os seguintes ?ndices (ver migration 001):
     * - idx_pedidos_kanban_principal: (ativo, data_pedido, status)
     * - idx_pedidos_cliente_id: Para JOIN com clientes
     * - idx_clientes_id: Para JOIN reverso
     * - idx_pedidos_observacoes_trgm: Para busca ILIKE em observa??es
     * - idx_clientes_nome_trgm: Para busca ILIKE em nome do cliente
     * 
     * PERFORMANCE: ~50ms para 10.000 registros com ?ndices
     * 
     * @param array $filters Filtros: status, cliente_id, busca, data_inicio, data_fim, ativo
     * @param int $perPage Itens por p?gina
     * @return array ['dados' => array, 'total' => int, 'pagina' => int, 'total_paginas' => int]
     */
    public function findAll(array $filters = [], int $perPage = 15): array
    {
        $pagina = max(1, (int)($filters['page'] ?? $filters['pagina'] ?? 1));
        $offset = ($pagina - 1) * $perPage;

        [$where, $params] = $this->buildWhere($filters);

        // Query otimizada com JOIN ?nico (evita N+1)
        // O LEFT JOIN traz o nome do cliente de uma vez, eliminando consultas adicionais
        $sql = "
            SELECT p.*, c.nome AS cliente_nome
            FROM " . self::TABLE . " p
            LEFT JOIN clientes c ON c.id::text = p.cliente_id::text
            {$where}
            ORDER BY p.data_pedido DESC, p.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dados = array_map(
            static fn(array $row): array => Pedido::fromArray($row)->toArray() + ['cliente_nome' => $row['cliente_nome'] ?? null],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        // COUNT otimizado: usa mesmos ?ndices da query principal
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM " . self::TABLE . " p LEFT JOIN clientes c ON c.id::text = p.cliente_id::text {$where}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $pagina,
            'itens_por_pagina' => $perPage,
            'total_paginas' => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    public function update(string $id, array $data): Pedido
    {
        $atual = $this->findById($id);
        if ($atual === null) {
            throw new RuntimeException('Pedido n?o encontrado para atualiza??o.');
        }

        $setParts = [
            'cliente_id = :cliente_id',
            'orcamento_id = :orcamento_id',
            'data_pedido = :data_pedido',
            'status = :status',
            'valor_total = :valor_total',
            'observacoes = :observacoes',
            'data_faturamento = :data_faturamento',
            'data_entrega_prevista = :data_entrega_prevista',
            'data_entrega_realizada = :data_entrega_realizada',
            'ativo = :ativo',
            'updated_at = NOW()',
        ];

        $params = [
            ':id' => $id,
            ':cliente_id' => $this->intOrNull($data['cliente_id'] ?? $atual->cliente_id),
            ':orcamento_id' => $this->intOrNull($data['orcamento_id'] ?? $atual->orcamento_id),
            ':data_pedido' => $data['data_pedido'] ?? $atual->data_pedido,
            ':status' => strtoupper((string)($data['status'] ?? $atual->status)),
            ':valor_total' => (float)($data['valor_total'] ?? $atual->valor_total),
            ':observacoes' => array_key_exists('observacoes', $data) ? $data['observacoes'] : $atual->observacoes,
            ':data_faturamento' => $this->dateTimeOrNull(array_key_exists('data_faturamento', $data) ? $data['data_faturamento'] : $atual->data_faturamento),
            ':data_entrega_prevista' => $this->dateOrNull(array_key_exists('data_entrega_prevista', $data) ? $data['data_entrega_prevista'] : $atual->data_entrega_prevista),
            ':data_entrega_realizada' => $this->dateOrNull(array_key_exists('data_entrega_realizada', $data) ? $data['data_entrega_realizada'] : $atual->data_entrega_realizada),
            ':ativo' => $this->boolToPg(array_key_exists('ativo', $data) ? $data['ativo'] : $atual->ativo),
        ];

        if ($this->hasDescontoColumns()) {
            $setParts[] = 'desconto_tipo = :desconto_tipo';
            $setParts[] = 'desconto_valor = :desconto_valor';
            $params[':desconto_tipo'] = $this->descontoTipoOrNull($data['desconto_tipo'] ?? $atual->desconto_tipo);
            $params[':desconto_valor'] = round((float)($data['desconto_valor'] ?? $atual->desconto_valor), 2);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE " . self::TABLE . "
             SET " . implode(",\n                 ", $setParts) . "
             WHERE id = :id"
        );

        $stmt->execute($params);

        $pedido = $this->findById($id);
        if ($pedido === null) {
            throw new RuntimeException('Falha ao atualizar pedido.');
        }

        return $pedido;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE " . self::TABLE . " SET ativo = FALSE, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Constr?i cl?usula WHERE din?mica com filtros
     * 
     * OTIMIZA??O: Ordem dos filtros importa para uso eficiente de ?ndices
     * 1. ativo (?ndice idx_pedidos_kanban_principal)
     * 2. data_pedido (?ndice idx_pedidos_kanban_principal)
     * 3. status (?ndice idx_pedidos_status)
     * 4. cliente_id (?ndice idx_pedidos_cliente_id)
     * 5. busca textual (?ndices GIN trigram)
     * 
     * @param array $filters
     * @return array [string $where, array $params]
     */
    private function buildWhere(array $filters): array
    {
        $conds = [];
        $params = [];

        $kanbanMode = !empty($filters['kanban_mode']);

        // Filtro 1: ativo (usado no ?ndice composto principal)
        if (isset($filters['ativo'])) {
            $conds[] = 'p.ativo = :ativo';
            $params[':ativo'] = (bool)$filters['ativo'];
        } else {
            $conds[] = '(p.ativo = TRUE OR p.status = :status_cancelado)';
            $params[':status_cancelado'] = Pedido::STATUS_CANCELADO;
        }

        // Filtro 2: status (?ndice idx_pedidos_status)
        if (!empty($filters['status'])) {
            $conds[] = 'p.status = :status';
            $params[':status'] = strtoupper((string)$filters['status']);
        }

        // Filtro 3: cliente_id (?ndice idx_pedidos_cliente_id)
        if (!empty($filters['cliente_id'])) {
            $conds[] = 'p.cliente_id::text = :cliente_id';
            $params[':cliente_id'] = (string)$filters['cliente_id'];
        }

        // Filtros 4+5: l?gica de visibilidade por data no Kanban vs Lista
        if ($kanbanMode) {
            // CANCELADO nunca aparece no kanban
            $conds[] = "p.status != 'CANCELADO'";

            // Regras de visibilidade:
            // - FATURADO:  apenas se faturado hoje
            // - CONCLUIDO: apenas se conclu?do hoje (updated_at)
            // - Demais (PENDENTE, EM_PROCESSO, APROVADO, RASCUNHO):
            //     aparecem se data_entrega_prevista >= hoje OU sem data prevista
            $dataInicio = (string)($filters['data_inicio'] ?? date('Y-m-d'));
            $conds[] = "(
                (p.status = 'FATURADO'  AND (p.data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date = CURRENT_DATE)
                OR
                (p.status = 'CONCLUIDO' AND (p.updated_at AT TIME ZONE 'America/Sao_Paulo')::date = CURRENT_DATE)
                OR
                (p.status NOT IN ('FATURADO','CONCLUIDO') AND (p.data_entrega_prevista IS NULL OR p.data_entrega_prevista >= :data_inicio_kanban))
            )";
            $params[':data_inicio_kanban'] = $dataInicio;
        } else {
            // Filtro 4: data_inicio (modo lista)
            if (!empty($filters['data_inicio'])) {
                $conds[] = "(p.data_pedido AT TIME ZONE 'America/Sao_Paulo')::date >= :data_inicio";
                $params[':data_inicio'] = (string)$filters['data_inicio'];
            }
            // Filtro 5: data_fim (modo lista)
            if (!empty($filters['data_fim'])) {
                $conds[] = "(p.data_pedido AT TIME ZONE 'America/Sao_Paulo')::date <= :data_fim";
                $params[':data_fim'] = (string)$filters['data_fim'];
            }
        }

        // Filtro 6: busca textual (?ndices GIN trigram para ILIKE r?pido)
        if (!empty($filters['busca'])) {
            $conds[] = '(p.id::text ILIKE :busca OR c.nome ILIKE :busca OR p.observacoes ILIKE :busca)';
            $params[':busca'] = '%' . $filters['busca'] . '%';
        }

        $where = empty($conds) ? '' : ('WHERE ' . implode(' AND ', $conds));

        return [$where, $params];
    }

    private function buscarCliente(?string $clienteId): ?array
    {
        if ($clienteId === null || $clienteId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM clientes WHERE id::text = :id LIMIT 1');
        $stmt->execute([':id' => $clienteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function buscarOrcamento(?string $orcamentoId): ?array
    {
        if ($orcamentoId === null || $orcamentoId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM orcamentos WHERE id::text = :id LIMIT 1');
        $stmt->execute([':id' => $orcamentoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function dateTimeOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    /**
     * Atualiza apenas o status do pedido.
     * Usado para opera??es simples de mudan?a de status (ex: Kanban).
     */
    public function atualizarStatusSimples(string $id, string $novoStatus): bool
    {
        if (!in_array($novoStatus, Pedido::STATUS_VALIDOS, true)) {
            throw new RuntimeException('Status inv?lido: ' . $novoStatus);
        }

        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE . "
            SET status = :status, updated_at = NOW()
            WHERE id = :id AND ativo = TRUE
        ");

        $stmt->execute([
            ':status' => strtoupper($novoStatus),
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function boolToPg(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    private function hasUsuarioIdColumn(): bool
    {
        if ($this->hasUsuarioIdColumnCache !== null) {
            return $this->hasUsuarioIdColumnCache;
        }

        $stmt = $this->pdo->prepare("\n            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'pedidos'
              AND column_name = 'usuario_id'
            LIMIT 1
        ");
        $stmt->execute();

        $this->hasUsuarioIdColumnCache = (bool)$stmt->fetchColumn();
        return $this->hasUsuarioIdColumnCache;
    }

    private function hasDescontoColumns(): bool
    {
        if ($this->hasDescontoColumnsCache !== null) {
            return $this->hasDescontoColumnsCache;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'pedidos'
              AND column_name IN ('desconto_tipo', 'desconto_valor')
        ");
        $stmt->execute();

        $this->hasDescontoColumnsCache = ((int)$stmt->fetchColumn()) === 2;
        return $this->hasDescontoColumnsCache;
    }

    private function descontoTipoOrNull(mixed $value): ?string
    {
        $tipo = strtoupper(trim((string)$value));
        return in_array($tipo, ['VALOR', 'PERCENTUAL'], true) ? $tipo : null;
    }
}
