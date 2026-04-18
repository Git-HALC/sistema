<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Support\DashboardApiException;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

class DashboardApiService
{
    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct(private readonly PDO $pdo) {}

    public function getKpis(): array
    {
        $currentMonthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $currentMonthEnd = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        $previousMonthStart = (new DateTimeImmutable('first day of previous month'))->format('Y-m-d');
        $previousMonthEnd = (new DateTimeImmutable('last day of previous month'))->format('Y-m-d');

        $clientesAtivos = (int)$this->scalar("
            SELECT COUNT(*)
            FROM clientes
            WHERE ativo = TRUE
        ");

        $clientesAtivosAnterior = $this->hasColumn('clientes', 'created_at')
            ? (int)$this->scalar("
                SELECT COUNT(*)
                FROM clientes
                WHERE ativo = TRUE
                  AND created_at < CAST(:inicio_atual AS date)
            ", [':inicio_atual' => $currentMonthStart])
            : $clientesAtivos;

        $pedidosAtual = $this->fetchAssoc("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(valor_total), 0) AS valor_total
            FROM pedidos
            WHERE ativo = TRUE
              AND status <> 'CANCELADO'
              AND (data_pedido AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
        ", [':inicio' => $currentMonthStart, ':fim' => $currentMonthEnd]);

        $pedidosAnterior = $this->fetchAssoc("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(valor_total), 0) AS valor_total
            FROM pedidos
            WHERE ativo = TRUE
              AND status <> 'CANCELADO'
              AND (data_pedido AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
        ", [':inicio' => $previousMonthStart, ':fim' => $previousMonthEnd]);

        $servicosAndamentoAtual = (int)$this->scalar("
            SELECT COUNT(*)
            FROM servicos
            WHERE ativo = TRUE
              AND status IN ('PENDENTE', 'EM_PROCESSO')
        ");

        $servicosAndamentoAnterior = (int)$this->scalar("
            SELECT COUNT(*)
            FROM servicos
            WHERE ativo = TRUE
              AND status IN ('PENDENTE', 'EM_PROCESSO')
              AND (data_servico AT TIME ZONE 'America/Sao_Paulo')::date < :inicio_atual
        ", [':inicio_atual' => $currentMonthStart]);

        $faturamentoAtual = $this->fetchAssoc("
            SELECT
                COALESCE(SUM(pedidos_valor), 0) AS pedidos,
                COALESCE(SUM(servicos_valor), 0) AS servicos
            FROM (
                SELECT COALESCE(SUM(valor_total), 0) AS pedidos_valor, 0::numeric AS servicos_valor
                FROM pedidos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim

                UNION ALL

                SELECT 0::numeric AS pedidos_valor, COALESCE(SUM(valor_total), 0) AS servicos_valor
                FROM servicos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
            ) base
        ", [':inicio' => $currentMonthStart, ':fim' => $currentMonthEnd]);

        $faturamentoAnterior = $this->fetchAssoc("
            SELECT
                COALESCE(SUM(pedidos_valor), 0) AS pedidos,
                COALESCE(SUM(servicos_valor), 0) AS servicos
            FROM (
                SELECT COALESCE(SUM(valor_total), 0) AS pedidos_valor, 0::numeric AS servicos_valor
                FROM pedidos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim

                UNION ALL

                SELECT 0::numeric AS pedidos_valor, COALESCE(SUM(valor_total), 0) AS servicos_valor
                FROM servicos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
            ) base
        ", [':inicio' => $previousMonthStart, ':fim' => $previousMonthEnd]);

        return [
            'periodo' => [
                'inicio' => $currentMonthStart,
                'fim' => $currentMonthEnd,
                'comparacao_inicio' => $previousMonthStart,
                'comparacao_fim' => $previousMonthEnd,
            ],
            'kpis' => [
                'clientes_ativos' => [
                    'valor' => $clientesAtivos,
                    'variacao_percentual' => $this->percentChange($clientesAtivos, $clientesAtivosAnterior),
                    'variacao_absoluta' => $clientesAtivos - $clientesAtivosAnterior,
                    'referencia' => 'vs inicio do mes',
                    'sparkline' => $this->clientesAtivosSparkline(),
                    'href' => $this->adminUrl('admin/clientes.php'),
                ],
                'pedidos_mes' => [
                    'valor' => (int)($pedidosAtual['total'] ?? 0),
                    'valor_financeiro' => (float)($pedidosAtual['valor_total'] ?? 0),
                    'variacao_percentual' => $this->percentChange(
                        (float)($pedidosAtual['total'] ?? 0),
                        (float)($pedidosAnterior['total'] ?? 0)
                    ),
                    'variacao_absoluta' => (int)($pedidosAtual['total'] ?? 0) - (int)($pedidosAnterior['total'] ?? 0),
                    'referencia' => 'vs mes anterior',
                    'sparkline' => $this->pedidosSparkline(),
                    'href' => $this->adminUrl('admin/pedidos.php?action=listar'),
                ],
                'servicos_em_andamento' => [
                    'valor' => $servicosAndamentoAtual,
                    'variacao_percentual' => $this->percentChange($servicosAndamentoAtual, $servicosAndamentoAnterior),
                    'variacao_absoluta' => $servicosAndamentoAtual - $servicosAndamentoAnterior,
                    'referencia' => 'baseado no estoque atual de servicos abertos',
                    'sparkline' => $this->servicosEmAndamentoSparkline(),
                    'href' => $this->adminUrl('admin/servicos.php?action=kanban'),
                ],
                'faturamento_mes' => [
                    'valor' => (float)($faturamentoAtual['pedidos'] ?? 0) + (float)($faturamentoAtual['servicos'] ?? 0),
                    'pedidos' => (float)($faturamentoAtual['pedidos'] ?? 0),
                    'servicos' => (float)($faturamentoAtual['servicos'] ?? 0),
                    'variacao_percentual' => $this->percentChange(
                        (float)($faturamentoAtual['pedidos'] ?? 0) + (float)($faturamentoAtual['servicos'] ?? 0),
                        (float)($faturamentoAnterior['pedidos'] ?? 0) + (float)($faturamentoAnterior['servicos'] ?? 0)
                    ),
                    'variacao_absoluta' => (
                        (float)($faturamentoAtual['pedidos'] ?? 0) + (float)($faturamentoAtual['servicos'] ?? 0)
                    ) - (
                        (float)($faturamentoAnterior['pedidos'] ?? 0) + (float)($faturamentoAnterior['servicos'] ?? 0)
                    ),
                    'referencia' => 'vs mes anterior',
                    'sparkline' => $this->faturamentoSparkline(),
                    'href' => $this->adminUrl('admin/financeiro/dashboard.php'),
                ],
            ],
        ];
    }

    public function getFaturamento(string $period): array
    {
        $period = strtoupper(trim($period));
        $config = match ($period) {
            '7D' => ['days' => 7, 'step' => '1 day', 'bucket' => 'day', 'format' => 'DD/MM'],
            '30D' => ['days' => 30, 'step' => '1 day', 'bucket' => 'day', 'format' => 'DD/MM'],
            '90D' => ['days' => 90, 'step' => '1 day', 'bucket' => 'day', 'format' => 'DD/MM'],
            '12M' => ['days' => 365, 'step' => '1 month', 'bucket' => 'month', 'format' => 'MM/YYYY'],
            default => throw new DashboardApiException('Periodo invalido.', 400),
        };

        $start = $period === '12M'
            ? (new DateTimeImmutable('first day of -11 months'))->format('Y-m-01')
            : (new DateTimeImmutable('-' . ($config['days'] - 1) . ' days'))->format('Y-m-d');
        $end = (new DateTimeImmutable('today'))->format('Y-m-d');

        $labelSql = $config['bucket'] === 'month'
            ? "TO_CHAR(serie.data_ref, 'MM/YYYY')"
            : "TO_CHAR(serie.data_ref, 'DD/MM')";
        $pedidoDataRef = $config['bucket'] === 'month'
            ? "DATE_TRUNC('month', data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date"
            : "(data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date";
        $servicoDataRef = $pedidoDataRef;

        $sql = "
            WITH serie AS (
                SELECT generate_series(CAST(:inicio AS date), CAST(:fim AS date), INTERVAL '{$config['step']}')::date AS data_ref
            ),
            pedidos AS (
                SELECT {$pedidoDataRef} AS data_ref,
                       COALESCE(SUM(valor_total), 0) AS total
                FROM pedidos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN CAST(:inicio AS date) AND CAST(:fim AS date)
                GROUP BY 1
            ),
            servicos AS (
                SELECT {$servicoDataRef} AS data_ref,
                       COALESCE(SUM(valor_total), 0) AS total
                FROM servicos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN CAST(:inicio AS date) AND CAST(:fim AS date)
                GROUP BY 1
            )
            SELECT serie.data_ref,
                   {$labelSql} AS label,
                   COALESCE(pedidos.total, 0) AS pedidos,
                   COALESCE(servicos.total, 0) AS servicos
            FROM serie
            LEFT JOIN pedidos ON pedidos.data_ref = serie.data_ref
            LEFT JOIN servicos ON servicos.data_ref = serie.data_ref
            ORDER BY serie.data_ref ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':inicio' => $start, ':fim' => $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $labels = [];
        $pedidos = [];
        $servicos = [];
        $total = [];

        foreach ($rows as $row) {
            $labels[] = (string)($row['label'] ?? '');
            $pedidoValor = (float)($row['pedidos'] ?? 0);
            $servicoValor = (float)($row['servicos'] ?? 0);
            $pedidos[] = round($pedidoValor, 2);
            $servicos[] = round($servicoValor, 2);
            $total[] = round($pedidoValor + $servicoValor, 2);
        }

        return [
            'periodo' => $period,
            'inicio' => $start,
            'fim' => $end,
            'agrupamento' => $config['bucket'],
            'labels' => $labels,
            'series' => [
                'pedidos' => $pedidos,
                'servicos' => $servicos,
                'total' => $total,
            ],
        ];
    }

    public function getAtividadeRecente(int $page, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT *
            FROM (
                SELECT
                    'pedido' AS tipo,
                    p.id::text AS id,
                    p.numero,
                    COALESCE(c.nome, 'Sem cliente') AS cliente_nome,
                    p.status,
                    p.valor_total,
                    COALESCE(p.updated_at, p.data_pedido) AS data_evento
                FROM pedidos p
                LEFT JOIN clientes c ON c.id::text = p.cliente_id::text
                WHERE p.ativo = TRUE

                UNION ALL

                SELECT
                    'servico' AS tipo,
                    s.id::text AS id,
                    s.numero,
                    COALESCE(NULLIF(s.nome_cliente, ''), c.nome, 'Sem cliente') AS cliente_nome,
                    s.status,
                    s.valor_total,
                    COALESCE(s.updated_at, s.data_servico) AS data_evento
                FROM servicos s
                LEFT JOIN clientes c ON c.id = s.cliente_id
                WHERE s.ativo = TRUE
            ) atividade
            ORDER BY data_evento DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = (int)$this->scalar("
            SELECT COUNT(*)
            FROM (
                SELECT id FROM pedidos WHERE ativo = TRUE
                UNION ALL
                SELECT id FROM servicos WHERE ativo = TRUE
            ) atividade
        ");

        $items = array_map(function (array $row): array {
            $tipo = (string)($row['tipo'] ?? '');
            $id = (string)($row['id'] ?? '');

            return [
                'tipo' => $tipo,
                'id' => $id,
                'numero' => isset($row['numero']) ? (int)$row['numero'] : null,
                'cliente_nome' => (string)($row['cliente_nome'] ?? 'Sem cliente'),
                'status' => (string)($row['status'] ?? ''),
                'valor_total' => (float)($row['valor_total'] ?? 0),
                'data_evento' => (string)($row['data_evento'] ?? ''),
                'href' => $tipo === 'pedido'
                    ? $this->adminUrl('admin/pedidos.php?action=show&id=' . urlencode($id))
                    : $this->adminUrl('admin/servicos.php?action=visualizar&id=' . urlencode($id)),
            ];
        }, $rows);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    private function clientesAtivosSparkline(): array
    {
        $current = (int)$this->scalar("
            SELECT COUNT(*)
            FROM clientes
            WHERE ativo = TRUE
        ");

        if (!$this->hasColumn('clientes', 'created_at')) {
            return array_fill(0, 7, $current);
        }

        $start = new DateTimeImmutable('-6 days');
        $values = [];
        foreach (new DatePeriod($start, new DateInterval('P1D'), 7) as $day) {
            $values[] = (int)$this->scalar("
                SELECT COUNT(*)
                FROM clientes
                WHERE ativo = TRUE
                  AND created_at < (:limite::date + INTERVAL '1 day')
            ", [':limite' => $day->format('Y-m-d')]);
        }

        return $values;
    }

    private function pedidosSparkline(): array
    {
        return $this->dailySeries("
            SELECT (data_pedido AT TIME ZONE 'America/Sao_Paulo')::date AS data_ref,
                   COUNT(*) AS total
            FROM pedidos
            WHERE ativo = TRUE
              AND status <> 'CANCELADO'
              AND (data_pedido AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
            GROUP BY 1
        ");
    }

    private function servicosEmAndamentoSparkline(): array
    {
        return $this->dailySeries("
            SELECT (data_servico AT TIME ZONE 'America/Sao_Paulo')::date AS data_ref,
                   COUNT(*) AS total
            FROM servicos
            WHERE ativo = TRUE
              AND status IN ('PENDENTE', 'EM_PROCESSO')
              AND (data_servico AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
            GROUP BY 1
        ");
    }

    private function faturamentoSparkline(): array
    {
        return $this->dailySeries("
            SELECT data_ref, SUM(total) AS total
            FROM (
                SELECT (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date AS data_ref,
                       COALESCE(SUM(valor_total), 0) AS total
                FROM pedidos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
                GROUP BY 1

                UNION ALL

                SELECT (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date AS data_ref,
                       COALESCE(SUM(valor_total), 0) AS total
                FROM servicos
                WHERE ativo = TRUE
                  AND status = 'FATURADO'
                  AND data_faturamento IS NOT NULL
                  AND (data_faturamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN :inicio AND :fim
                GROUP BY 1
            ) faturamento
            GROUP BY data_ref
        ", true);
    }

    private function dailySeries(string $aggregateSql, bool $float = false): array
    {
        $start = new DateTimeImmutable('-6 days');
        $end = new DateTimeImmutable('today');

        $stmt = $this->pdo->prepare($aggregateSql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d'),
            ':fim' => $end->format('Y-m-d'),
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string)$row['data_ref']] = $float
                ? round((float)($row['total'] ?? 0), 2)
                : (int)($row['total'] ?? 0);
        }

        $values = [];
        foreach (new DatePeriod($start, new DateInterval('P1D'), 7) as $day) {
            $key = $day->format('Y-m-d');
            $values[] = $indexed[$key] ?? ($float ? 0.0 : 0);
        }

        return $values;
    }

    private function adminUrl(string $path): string
    {
        if (function_exists('tenantUrl')) {
            return \tenantUrl($path);
        }

        return '/sistema_dm/public/' . ltrim($path, '/');
    }

    private function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = :table
              AND column_name = :column
            LIMIT 1
        ");
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return $this->columnCache[$cacheKey] = (bool)$stmt->fetchColumn();
    }

    private function fetchAssoc(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function percentChange(float|int $current, float|int $previous): float
    {
        $current = (float)$current;
        $previous = (float)$previous;

        if (abs($previous) < 0.00001) {
            return abs($current) < 0.00001 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
