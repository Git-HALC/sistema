<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Support\DashboardApiException;
use DateTimeImmutable;
use PDO;

/**
 * API do Dashboard Geral — centrado em pdv_vendas/pdv_venda_itens.
 *
 * Endpoints servidos:
 *  - GET dashboard/kpis               → getKpis()
 *  - GET dashboard/faturamento        → getFaturamento($period)
 *  - GET dashboard/atividade-recente  → getAtividadeRecente($page)
 *  - GET dashboard/top-itens          → getTopItens($period)
 *  - GET dashboard/formas-pagamento   → getFormasPagamento($period)
 *  - GET dashboard/vendas-hora        → getVendasPorHora($period)
 */
class DashboardApiService
{
    private readonly DashboardGeralRepository $repo;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repo = new DashboardGeralRepository($pdo);
    }

    public function getKpis(): array
    {
        [$ini, $fim] = $this->periodoMesAtual();
        [$iniAnterior, $fimAnterior] = $this->periodoMesAnterior();

        $atual = $this->repo->kpiVendas($ini, $fim);
        $anterior = $this->repo->kpiVendas($iniAnterior, $fimAnterior);

        $clientesNovosAtual = $this->repo->kpiClientesNovos($ini, $fim);
        $clientesNovosAnterior = $this->repo->kpiClientesNovos($iniAnterior, $fimAnterior);

        $estoqueCriticoCount = $this->repo->kpiEstoqueCriticoCount();

        return [
            'periodo' => [
                'inicio' => $ini,
                'fim' => $fim,
                'comparacao_inicio' => $iniAnterior,
                'comparacao_fim' => $fimAnterior,
            ],
            'kpis' => [
                'faturamento_mes' => [
                    'valor' => $atual['total'],
                    'variacao_percentual' => $this->percentChange($atual['total'], $anterior['total']),
                    'variacao_absoluta' => round($atual['total'] - $anterior['total'], 2),
                    'referencia' => 'vs mes anterior',
                    'href' => $this->adminUrl('admin/financeiro/dashboard.php'),
                ],
                'vendas_mes' => [
                    'valor' => $atual['quantidade'],
                    'variacao_percentual' => $this->percentChange($atual['quantidade'], $anterior['quantidade']),
                    'variacao_absoluta' => $atual['quantidade'] - $anterior['quantidade'],
                    'referencia' => 'vs mes anterior',
                    'href' => $this->adminUrl('admin/relatorios/financeiros/relatorio_vendas_pdv.php'),
                ],
                'ticket_medio' => [
                    'valor' => $atual['ticket_medio'],
                    'variacao_percentual' => $this->percentChange($atual['ticket_medio'], $anterior['ticket_medio']),
                    'variacao_absoluta' => round($atual['ticket_medio'] - $anterior['ticket_medio'], 2),
                    'referencia' => 'vs mes anterior',
                    'href' => $this->adminUrl('admin/relatorios/financeiros/relatorio_vendas_pdv.php'),
                ],
                'clientes_novos' => [
                    'valor' => $clientesNovosAtual,
                    'variacao_percentual' => $this->percentChange($clientesNovosAtual, $clientesNovosAnterior),
                    'variacao_absoluta' => $clientesNovosAtual - $clientesNovosAnterior,
                    'referencia' => 'vs mes anterior',
                    'href' => $this->adminUrl('admin/clientes.php'),
                ],
                'estoque_critico' => [
                    'valor' => $estoqueCriticoCount,
                    'variacao_percentual' => 0.0,
                    'variacao_absoluta' => 0,
                    'referencia' => 'produtos abaixo do minimo',
                    'href' => $this->adminUrl('admin/produtos.php'),
                ],
            ],
        ];
    }

    public function getFaturamento(string $period): array
    {
        $period = strtoupper(trim($period));
        $days = match ($period) {
            '7D' => 7,
            '30D' => 30,
            '90D' => 90,
            default => throw new DashboardApiException('Periodo invalido.', 400),
        };

        $rows = $this->repo->vendasPorDia($days);
        $labels = [];
        $totais = [];
        foreach ($rows as $r) {
            $labels[] = (string)$r['dia'];
            $totais[] = round((float)$r['total'], 2);
        }

        return [
            'periodo' => $period,
            'labels' => $labels,
            'series' => [
                'vendas' => $totais,
            ],
        ];
    }

    public function getTopItens(string $period): array
    {
        [$ini, $fim] = $this->periodoPorTexto($period);
        $rows = $this->repo->topItensVendidos($ini, $fim, 5);

        return [
            'labels' => array_map(static fn ($r) => (string)$r['nome'], $rows),
            'quantidades' => array_map(static fn ($r) => (float)$r['qtd_vendida'], $rows),
            'totais' => array_map(static fn ($r) => round((float)$r['total_vendido'], 2), $rows),
        ];
    }

    public function getFormasPagamento(string $period): array
    {
        [$ini, $fim] = $this->periodoPorTexto($period);
        $rows = $this->repo->vendasPorForma($ini, $fim);

        return [
            'labels' => array_map(static fn ($r) => (string)$r['forma'], $rows),
            'totais' => array_map(static fn ($r) => round((float)$r['total'], 2), $rows),
            'tipos' => array_map(static fn ($r) => (string)$r['tipo'], $rows),
        ];
    }

    public function getVendasPorHora(string $period): array
    {
        [$ini, $fim] = $this->periodoPorTexto($period);
        $rows = $this->repo->vendasPorHora($ini, $fim);

        $labels = [];
        $quantidades = [];
        $totais = [];
        foreach ($rows as $r) {
            $labels[] = sprintf('%02dh', (int)$r['hora']);
            $quantidades[] = (int)$r['quantidade'];
            $totais[] = round((float)$r['total'], 2);
        }

        return [
            'labels' => $labels,
            'quantidades' => $quantidades,
            'totais' => $totais,
        ];
    }

    public function getAtividadeRecente(int $page, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT v.id, v.numero, v.valor_total, v.created_at, v.status, v.origem,
                    COALESCE(fp.nome, 'A definir') AS forma,
                    COALESCE(cli.nome, 'Consumidor') AS cliente_nome
               FROM pdv_vendas v
               LEFT JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
               LEFT JOIN clientes cli ON cli.id = v.cliente_id
              ORDER BY v.created_at DESC
              LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM pdv_vendas")->fetchColumn();

        $items = array_map(function (array $r): array {
            return [
                'id' => (string)$r['id'],
                'numero' => (int)$r['numero'],
                'cliente_nome' => (string)$r['cliente_nome'],
                'forma' => (string)$r['forma'],
                'status' => (string)$r['status'],
                'origem' => (string)$r['origem'],
                'valor_total' => (float)$r['valor_total'],
                'data_evento' => (string)$r['created_at'],
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

    private function periodoMesAtual(): array
    {
        $hoje = new DateTimeImmutable('today');
        return [
            $hoje->modify('first day of this month')->format('Y-m-d'),
            $hoje->modify('last day of this month')->format('Y-m-d'),
        ];
    }

    private function periodoMesAnterior(): array
    {
        $hoje = new DateTimeImmutable('today');
        return [
            $hoje->modify('first day of previous month')->format('Y-m-d'),
            $hoje->modify('last day of previous month')->format('Y-m-d'),
        ];
    }

    private function periodoPorTexto(string $period): array
    {
        $period = strtoupper(trim($period));
        $dias = match ($period) {
            '7D' => 7,
            '30D' => 30,
            '90D' => 90,
            default => 30,
        };
        $fim = new DateTimeImmutable('today');
        $ini = $fim->modify('-' . ($dias - 1) . ' days');
        return [$ini->format('Y-m-d'), $fim->format('Y-m-d')];
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

    private function adminUrl(string $path): string
    {
        if (function_exists('tenantUrl')) {
            return \tenantUrl($path);
        }
        return '/sistema_dm/public/' . ltrim($path, '/');
    }
}
