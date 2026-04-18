<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use PDO;
use RuntimeException;

/**
 * Repositorio de acesso a tabela pedido_nfe.
 */
class NotaFiscalRepository
{
    private const TABLE = 'pedido_nfe';

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Busca a nota fiscal de um pedido.
     */
    public function findByPedidoId(string $pedidoId): ?NotaFiscal
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE pedido_id = :pedido_id ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([':pedido_id' => $pedidoId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? NotaFiscal::fromArray($row) : null;
    }

    /**
     * Busca a nota fiscal de produto gerada a partir de um servico.
     */
    public function findByServicoId(string $servicoId): ?NotaFiscal
    {
        // Desabilitado na auditoria 2026-04-18: pedido_nfe.servico_id removida.
        return null;
    }

    /**
     * Busca uma NF-e pelo ID.
     */
    public function findById(string $id): ?NotaFiscal
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? NotaFiscal::fromArray($row) : null;
    }

    /**
     * Lista notas fiscais emitidas com paginacao e filtros.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function findAll(array $filters, int $perPage): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['pagina'] ?? 1));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->buildWhere($filters);

        $sql = "
            SELECT pn.*,
                   p.numero AS pedido_numero,
                   NULL::BIGINT AS servico_numero,
                   p.valor_total AS valor_total,
                   c.nome AS cliente_nome,
                   'PEDIDO' AS origem_tipo
            FROM " . self::TABLE . " pn
            LEFT JOIN pedidos p ON p.id = pn.pedido_id
            LEFT JOIN clientes c ON c.id::text = p.cliente_id::text
            {$where}
            ORDER BY pn.created_at DESC, pn.numero_nfe DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dados[] = NotaFiscal::fromArray($row)->toArray() + [
                'pedido_numero' => $row['pedido_numero'] ?? null,
                'servico_numero' => $row['servico_numero'] ?? null,
                'cliente_nome' => $row['cliente_nome'] ?? null,
                'valor_total' => isset($row['valor_total']) ? (float)$row['valor_total'] : null,
                'origem_tipo' => $row['origem_tipo'] ?? 'PEDIDO',
            ];
        }

        $stmtCount = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM " . self::TABLE . " pn
            LEFT JOIN pedidos p ON p.id = pn.pedido_id
            LEFT JOIN clientes c ON c.id::text = p.cliente_id::text
            {$where}
        ");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $page,
            'itens_por_pagina' => $perPage,
            'total_paginas' => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    /**
     * Cria um novo registro de NF-e.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): NotaFiscal
    {
        $id = (string)($data['id'] ?? self::uuid());

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                id, pedido_id, numero_nfe, chave_acesso, status, data_emissao, xml_nfe, created_at, updated_at
            ) VALUES (
                :id, :pedido_id, :numero_nfe, :chave_acesso, :status, :data_emissao, :xml_nfe, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':pedido_id' => $data['pedido_id'] ?? null,
            ':numero_nfe' => (int)$data['numero_nfe'],
            ':chave_acesso' => (string)$data['chave_acesso'],
            ':status' => strtoupper((string)$data['status']),
            ':data_emissao' => $data['data_emissao'] ?? date('Y-m-d H:i:s'),
            ':xml_nfe' => $data['xml_nfe'] ?? null,
        ]);

        $nfe = $this->findById($id);
        if ($nfe === null) {
            throw new RuntimeException('Falha ao criar registro de NF-e.');
        }

        return $nfe;
    }

    /**
     * Atualiza status e campos extras da NF-e.
     *
     * @param array<string, mixed> $extra
     */
    public function updateStatus(string $id, string $status, array $extra = []): bool
    {
        $sets = ['status = :status', 'updated_at = NOW()'];
        $params = [
            ':id' => $id,
            ':status' => strtoupper($status),
        ];

        foreach (['xml_nfe', 'chave_acesso', 'data_emissao'] as $column) {
            if (array_key_exists($column, $extra)) {
                $sets[] = $column . ' = :' . $column;
                $params[':' . $column] = $extra[$column];
            }
        }

        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Incrementa e retorna o proximo numero de NF-e.
     *
     * Deve ser chamado dentro de uma transacao ativa.
     */
    public function incrementarNumeroNfe(): int
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('incrementarNumeroNfe() deve ser executado dentro de uma transacao ativa.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE empresa_local
            SET proximo_numero_nfe = COALESCE(proximo_numero_nfe, 0) + 1,
                updated_at = NOW()
            WHERE id = (
                SELECT id FROM empresa_local ORDER BY id ASC LIMIT 1
            )
            RETURNING proximo_numero_nfe
        ");
        $stmt->execute();

        $numero = $stmt->fetchColumn();
        if ($numero === false) {
            throw new RuntimeException('Nao foi possivel incrementar o numero da NF-e.');
        }

        return (int)$numero;
    }

    /**
     * Gera UUID v4 para uso nos inserts.
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Monta a clausula WHERE da listagem.
     *
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'pn.status = :status';
            $params[':status'] = strtoupper((string)$filters['status']);
        }
        if (!empty($filters['data_inicio'])) {
            $conditions[] = "(pn.data_emissao AT TIME ZONE 'America/Sao_Paulo')::date >= :data_inicio";
            $params[':data_inicio'] = (string)$filters['data_inicio'];
        }
        if (!empty($filters['data_fim'])) {
            $conditions[] = "(pn.data_emissao AT TIME ZONE 'America/Sao_Paulo')::date <= :data_fim";
            $params[':data_fim'] = (string)$filters['data_fim'];
        }
        if (!empty($filters['cliente_id'])) {
            $conditions[] = '(p.cliente_id::text = :cliente_id OR s.cliente_id::text = :cliente_id)';
            $params[':cliente_id'] = (string)$filters['cliente_id'];
        }

        return [
            empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions),
            $params,
        ];
    }
}
