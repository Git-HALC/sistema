<?php

declare(strict_types=1);

namespace App\Modules\Financeiro;

use App\Support\DashboardApiException;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class FinanceiroDashboardApiService
{
    public function __construct(private readonly PDO $pdo) {}

    public function getKpis(): array
    {
        $currentMonthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $currentMonthEnd = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        $previousMonthStart = (new DateTimeImmutable('first day of previous month'))->format('Y-m-d');
        $previousMonthEnd = (new DateTimeImmutable('last day of previous month'))->format('Y-m-d');

        $current = $this->fluxoResumo($currentMonthStart, $currentMonthEnd);
        $previous = $this->fluxoResumo($previousMonthStart, $previousMonthEnd);
        $inadimplenciaAtual = $this->inadimplenciaEmAberto();
        $inadimplenciaAnterior = $this->inadimplenciaEmAberto($previousMonthEnd);

        // Saldo consolidado atual de todas as contas bancarias ativas.
        // Este e o "Saldo do periodo" exibido no dashboard — sempre reflete
        // a posicao corrente somada, nao um delta do periodo.
        $saldoAtualContas = (float)$this->pdo->query(
            'SELECT COALESCE(SUM(saldo_atual), 0) FROM contas WHERE ativo = TRUE'
        )->fetchColumn();

        // Para manter variacao vs mes anterior coerente, reconstitui o saldo
        // ao final do mes anterior: saldo_atual - (receitas_mes_atual - despesas_mes_atual).
        $saldoFechamentoMesAnterior = $saldoAtualContas - ($current['receitas'] - $current['despesas']);

        return [
            'periodo' => [
                'inicio' => $currentMonthStart,
                'fim' => $currentMonthEnd,
                'comparacao_inicio' => $previousMonthStart,
                'comparacao_fim' => $previousMonthEnd,
            ],
            'kpis' => [
                'receitas' => [
                    'valor' => $current['receitas'],
                    'variacao_percentual' => $this->percentChange($current['receitas'], $previous['receitas']),
                    'variacao_absoluta' => round($current['receitas'] - $previous['receitas'], 2),
                    'referencia' => 'vs mes anterior',
                    'sparkline' => $this->dailyFluxoSparkline('receitas'),
                ],
                'despesas' => [
                    'valor' => $current['despesas'],
                    'variacao_percentual' => $this->percentChange($current['despesas'], $previous['despesas']),
                    'variacao_absoluta' => round($current['despesas'] - $previous['despesas'], 2),
                    'referencia' => 'vs mes anterior',
                    'sparkline' => $this->dailyFluxoSparkline('despesas'),
                ],
                'lucro' => [
                    'valor' => round($saldoAtualContas, 2),
                    'variacao_percentual' => $this->percentChange($saldoAtualContas, $saldoFechamentoMesAnterior),
                    'variacao_absoluta' => round($saldoAtualContas - $saldoFechamentoMesAnterior, 2),
                    'referencia' => 'saldo consolidado das contas',
                    'sparkline' => $this->dailyFluxoSparkline('lucro'),
                ],
                'inadimplencia' => [
                    'valor' => $inadimplenciaAtual,
                    'variacao_percentual' => $this->percentChange($inadimplenciaAtual, $inadimplenciaAnterior),
                    'variacao_absoluta' => round($inadimplenciaAtual - $inadimplenciaAnterior, 2),
                    'referencia' => 'titulos vencidos em aberto',
                    'sparkline' => $this->inadimplenciaSparkline(),
                ],
            ],
        ];
    }

    public function getReceitasDespesas(int $year, ?int $month = null, ?int $categoryId = null): array
    {
        $year = max(2020, min(2100, $year));
        $month = $month !== null ? max(1, min(12, $month)) : null;

        if ($month !== null) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
        } else {
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-12-31', $year);
        }

        $params = [
            ':inicio' => $start,
            ':fim' => $end,
        ];
        $categoryFilter = '';
        if ($categoryId !== null) {
            $categoryFilter = ' AND fluxo.categoria_dre_id = :categoria_dre_id';
            $params[':categoria_dre_id'] = $categoryId;
        }

        if ($month !== null) {
            $sql = "
                WITH dias AS (
                    SELECT generate_series(
                        CAST(:inicio AS date),
                        CAST(:fim AS date),
                        INTERVAL '1 day'
                    )::date AS data_ref
                ),
                fluxo AS (
                    {$this->fluxoBaseSql()}
                )
                SELECT
                    dias.data_ref,
                    EXTRACT(DAY FROM dias.data_ref)::int AS day_number,
                    COALESCE(SUM(CASE WHEN fluxo.tipo = 'receita' THEN fluxo.valor ELSE 0 END), 0) AS receitas,
                    COALESCE(SUM(CASE WHEN fluxo.tipo = 'despesa' THEN fluxo.valor ELSE 0 END), 0) AS despesas
                FROM dias
                LEFT JOIN fluxo
                    ON fluxo.data_ref = dias.data_ref
                    {$categoryFilter}
                GROUP BY dias.data_ref
                ORDER BY dias.data_ref ASC
            ";
        } else {
            $sql = "
                WITH meses AS (
                    SELECT generate_series(
                        DATE_TRUNC('month', CAST(:inicio AS date)),
                        DATE_TRUNC('month', CAST(:fim AS date)),
                        INTERVAL '1 month'
                    )::date AS data_ref
                ),
                fluxo AS (
                    {$this->fluxoBaseSql()}
                )
                SELECT
                    meses.data_ref,
                    EXTRACT(MONTH FROM meses.data_ref)::int AS month_number,
                    COALESCE(SUM(CASE WHEN fluxo.tipo = 'receita' THEN fluxo.valor ELSE 0 END), 0) AS receitas,
                    COALESCE(SUM(CASE WHEN fluxo.tipo = 'despesa' THEN fluxo.valor ELSE 0 END), 0) AS despesas
                FROM meses
                LEFT JOIN fluxo
                    ON DATE_TRUNC('month', fluxo.data_ref)::date = meses.data_ref
                    {$categoryFilter}
                GROUP BY meses.data_ref
                ORDER BY meses.data_ref ASC
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $labels = [];
        $receitas = [];
        $despesas = [];
        $lucro = [];

        foreach ($rows as $row) {
            if ($month !== null) {
                $labels[] = str_pad((string)($row['day_number'] ?? ''), 2, '0', STR_PAD_LEFT);
            } else {
                $labels[] = $this->monthLabel((int)($row['month_number'] ?? 0));
            }
            $receita = round((float)($row['receitas'] ?? 0), 2);
            $despesa = round((float)($row['despesas'] ?? 0), 2);
            $receitas[] = $receita;
            $despesas[] = $despesa;
            $lucro[] = round($receita - $despesa, 2);
        }

        return [
            'year' => $year,
            'month' => $month,
            'inicio' => $start,
            'fim' => $end,
            'categoria_dre_id' => $categoryId,
            'granularidade' => $month !== null ? 'dia' : 'mes',
            'periodo_label' => $month !== null
                ? $this->monthLabel($month) . '/' . $year
                : 'Ano de ' . $year,
            'labels' => $labels,
            'series' => [
                'receitas' => $receitas,
                'despesas' => $despesas,
                'lucro' => $lucro,
            ],
        ];
    }

    public function getDreResumido(?string $inicio = null, ?string $fim = null): array
    {
        [$ini, $fimR] = $this->resolveRange($inicio, $fim);
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $dre = $repo->dreResumido($ini, $fimR);
        return [
            'inicio' => $ini,
            'fim' => $fimR,
            'valores' => array_map(static fn ($v) => round((float)$v, 2), $dre),
        ];
    }

    public function getFluxoSemanal(?string $inicio = null, ?string $fim = null): array
    {
        [$ini, $fimR] = $this->resolveRange($inicio, $fim);
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $rows = $repo->fluxoSemanal($ini, $fimR);

        $labels = [];
        $receitas = [];
        $despesas = [];
        foreach ($rows as $r) {
            $labels[] = (string)$r['semana'];
            $receitas[] = round((float)$r['receitas'], 2);
            $despesas[] = round((float)$r['despesas'], 2);
        }
        return [
            'inicio' => $ini,
            'fim' => $fimR,
            'labels' => $labels,
            'series' => [
                'receitas' => $receitas,
                'despesas' => $despesas,
            ],
        ];
    }

    public function getCrPorVencimento(): array
    {
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $rows = $repo->crPorVencimento();
        return [
            'labels' => array_map(static fn ($r) => (string)$r['faixa'], $rows),
            'totais' => array_map(static fn ($r) => round((float)$r['total'], 2), $rows),
        ];
    }

    public function getPorCategoria(string $tipo, ?string $inicio = null, ?string $fim = null): array
    {
        [$ini, $fimR] = $this->resolveRange($inicio, $fim);
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $rows = $tipo === 'receita'
            ? $repo->receitasPorCategoria($ini, $fimR)
            : $repo->despesasPorCategoria($ini, $fimR);

        return [
            'tipo' => $tipo,
            'inicio' => $ini,
            'fim' => $fimR,
            'labels' => array_map(static fn ($r) => (string)$r['categoria'], $rows),
            'totais' => array_map(static fn ($r) => round((float)$r['total'], 2), $rows),
        ];
    }

    public function getRecebimentosPorForma(?string $inicio = null, ?string $fim = null): array
    {
        [$ini, $fimR] = $this->resolveRange($inicio, $fim);
        $rows = (new DashboardFinanceiroRepository($this->pdo))->recebimentosPorForma($ini, $fimR);
        return [
            'inicio' => $ini,
            'fim' => $fimR,
            'labels' => array_map(static fn ($r) => $r['forma'], $rows),
            'totais' => array_map(static fn ($r) => $r['total'], $rows),
            'tipos' => array_map(static fn ($r) => $r['tipo'], $rows),
            'quantidades' => array_map(static fn ($r) => $r['quantidade'], $rows),
            'total_geral' => round(array_sum(array_column($rows, 'total')), 2),
        ];
    }

    public function getSaldoContas(): array
    {
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $rows = $repo->saldoContas();
        $total = array_sum(array_map(static fn ($r) => (float)$r['saldo_atual'], $rows));
        return [
            'total' => round($total, 2),
            'contas' => array_map(static fn ($r) => [
                'id' => (int)$r['id'],
                'nome' => (string)$r['nome'],
                'tipo' => (string)$r['tipo'],
                'banco' => (string)($r['banco'] ?? ''),
                'saldo_atual' => round((float)$r['saldo_atual'], 2),
            ], $rows),
        ];
    }

    public function getContasPagarPorVencimento(): array
    {
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $rows = $repo->contasPagarPorVencimento();
        return [
            'labels' => array_map(static fn ($r) => (string)$r['faixa'], $rows),
            'totais' => array_map(static fn ($r) => round((float)$r['total'], 2), $rows),
        ];
    }

    public function getUltimosLancamentos(int $limite = 10): array
    {
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $rows = $repo->ultimosLancamentos($limite);
        return [
            'items' => array_map(static fn ($r) => [
                'id' => (int)$r['id'],
                'tipo' => (string)$r['tipo'],
                'valor' => round((float)$r['valor'], 2),
                'descricao' => (string)($r['descricao'] ?? ''),
                'data' => (string)$r['data_movimentacao'],
                'conta' => (string)($r['conta_nome'] ?? ''),
                'categoria' => (string)($r['categoria_nome'] ?? ''),
                'protegido' => (bool)$r['protegido'],
            ], $rows),
        ];
    }

    public function getProjecaoMes(): array
    {
        $repo = new DashboardFinanceiroRepository($this->pdo);
        $p = $repo->projecaoMesAtual();
        return [
            'realizado' => round((float)$p['realizado'], 2),
            'projecao' => round((float)$p['projecao'], 2),
            'dias_passados' => (int)$p['dias_passados'],
            'dias_mes' => (int)$p['dias_mes'],
        ];
    }

    private function resolveRange(?string $inicio, ?string $fim): array
    {
        if (!$inicio || !$fim) {
            $hoje = new DateTimeImmutable('today');
            $inicio = $hoje->modify('first day of this month')->format('Y-m-d');
            $fim = $hoje->modify('last day of this month')->format('Y-m-d');
        }
        return [$inicio, $fim];
    }

    public function getHeatmap(int $year): array
    {
        $year = max(2020, min(2100, $year));
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);

        $stmt = $this->pdo->prepare("
            WITH fluxo AS (
                {$this->fluxoBaseSql()}
            )
            SELECT
                fluxo.data_ref,
                COALESCE(SUM(CASE WHEN fluxo.tipo = 'receita' THEN fluxo.valor ELSE 0 END), 0) AS receitas,
                COALESCE(SUM(CASE WHEN fluxo.tipo = 'despesa' THEN fluxo.valor ELSE 0 END), 0) AS despesas
            FROM fluxo
            GROUP BY fluxo.data_ref
            ORDER BY fluxo.data_ref ASC
        ");
        $stmt->execute([':inicio' => $start, ':fim' => $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string)$row['data_ref']] = [
                'receitas' => round((float)($row['receitas'] ?? 0), 2),
                'despesas' => round((float)($row['despesas'] ?? 0), 2),
            ];
        }

        $items = [];
        $cursor = new DateTimeImmutable($start);
        $endDate = new DateTimeImmutable($end);
        while ($cursor <= $endDate) {
            $dateKey = $cursor->format('Y-m-d');
            $valores = $indexed[$dateKey] ?? ['receitas' => 0.0, 'despesas' => 0.0];
            $dayOfWeek = (int)$cursor->format('N') - 1;
            $weekIndex = (int)floor(((int)$cursor->format('z') + ((int)(new DateTimeImmutable($start))->format('N') - 1)) / 7);

            $items[] = [
                'data' => $dateKey,
                'dia_semana' => $dayOfWeek,
                'semana_indice' => $weekIndex,
                'receitas' => $valores['receitas'],
                'despesas' => $valores['despesas'],
                'valor_total' => round($valores['receitas'] + $valores['despesas'], 2),
            ];

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return [
            'year' => $year,
            'items' => $items,
        ];
    }

    public function getContasPagarReceber(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $receber = $this->listarContasReceberAbertas($limit);
        $pagar = $this->listarContasPagarAbertas($limit);

        $formasPagamento = (new FormaPagamentoRepository($this->pdo))->listar(true);
        $contasBancarias = (new ContaRepository($this->pdo))->listar(true);
        // Tipos reais em categorias_dre (CHECK do banco, case-sensitive):
        // 'Receita','Despesa','Deducao','CPV','Despesa Operacional','Despesa Financeira','Tributo','Outras'
        $categoriasReceita = (new CategoriaDreRepository($this->pdo))->listar(['Receita'], true);
        $categoriasDespesa = (new CategoriaDreRepository($this->pdo))->listar(
            ['Despesa', 'Despesa Operacional', 'Despesa Financeira', 'CPV', 'Tributo'],
            true
        );

        return [
            'contas_receber' => $receber,
            'contas_pagar' => $pagar,
            'opcoes' => [
                'formas_pagamento' => array_map(
                    static fn(array $item): array => [
                        'id' => (int)($item['id'] ?? 0),
                        'nome' => (string)($item['nome'] ?? ''),
                        'tipo' => (string)($item['tipo'] ?? ''),
                        'conta_id' => isset($item['conta_id']) ? (int)$item['conta_id'] : null,
                        'adquirente_id' => isset($item['adquirente_id']) ? (int)$item['adquirente_id'] : null,
                    ],
                    $formasPagamento
                ),
                'contas_bancarias' => array_map(
                    static fn(array $item): array => [
                        'id' => (int)($item['id'] ?? 0),
                        'nome' => (string)($item['nome'] ?? ''),
                        'banco' => (string)($item['banco'] ?? ''),
                        'saldo_atual' => round((float)($item['saldo_atual'] ?? 0), 2),
                    ],
                    $contasBancarias
                ),
                'categorias_receita' => array_map(
                    static fn(array $item): array => [
                        'id' => (int)($item['id'] ?? 0),
                        'nome' => (string)($item['nome'] ?? ''),
                        'tipo' => (string)($item['tipo'] ?? ''),
                    ],
                    $categoriasReceita
                ),
                'categorias_despesa' => array_map(
                    static fn(array $item): array => [
                        'id' => (int)($item['id'] ?? 0),
                        'nome' => (string)($item['nome'] ?? ''),
                        'tipo' => (string)($item['tipo'] ?? ''),
                    ],
                    $categoriasDespesa
                ),
            ],
        ];
    }

    public function markContaPagarAsPaid(int $id, array $dados, int $userId): array
    {
        unset($userId);

        $repo = new ContaPagarRepository($this->pdo, new FinanceiroService($this->pdo));
        $service = new ContaPagarService($repo);
        $conta = $repo->buscarPorId($id);

        if ($conta === null) {
            throw new DashboardApiException('Conta a pagar nao encontrada.', 404);
        }

        $saldoAberto = $this->saldoAbertoConta($conta);
        if ($saldoAberto <= 0) {
            throw new DashboardApiException('Nao ha saldo pendente para baixa.', 400);
        }

        $payload = [
            'conta_id' => (int)$dados['conta_id'],
            'categoria_dre_id' => (int)$dados['categoria_dre_id'],
            'valor_pago' => isset($dados['valor_pago']) && $dados['valor_pago'] !== null
                ? (float)$dados['valor_pago']
                : $saldoAberto,
            'desconto_pagamento' => (float)($dados['desconto_pagamento'] ?? 0),
            'data_pagamento' => (string)($dados['data_pagamento'] ?? date('Y-m-d')),
        ];

        $resultado = $service->baixar($id, $payload);
        if (!($resultado['ok'] ?? false)) {
            throw new DashboardApiException((string)($resultado['erros'][0] ?? 'Nao foi possivel baixar a conta.'), 400);
        }

        $atualizada = $repo->buscarPorId($id);

        return [
            'ok' => true,
            'mensagem' => 'Conta a pagar baixada com sucesso.',
            'conta' => $this->normalizeContaPagar($atualizada ?: $conta),
        ];
    }

    public function markContaReceberAsPaid(int $id, array $dados, int $userId): array
    {
        unset($userId);

        $repo = new ContaReceberRepository($this->pdo, new FinanceiroService($this->pdo));
        $service = new ContaReceberService($repo);
        $conta = $repo->buscarPorId($id);

        if ($conta === null) {
            throw new DashboardApiException('Conta a receber nao encontrada.', 404);
        }

        $saldoAberto = $this->saldoAbertoConta($conta);
        if ($saldoAberto <= 0) {
            throw new DashboardApiException('Nao ha saldo pendente para recebimento.', 400);
        }

        $payload = [
            'forma_pagamento_id' => (int)$dados['forma_pagamento_id'],
            'categoria_dre_id' => (int)$dados['categoria_dre_id'],
            'valor_pago' => isset($dados['valor_pago']) && $dados['valor_pago'] !== null
                ? (float)$dados['valor_pago']
                : $saldoAberto,
            'desconto_recebimento' => (float)($dados['desconto_recebimento'] ?? 0),
            'data_pagamento' => (string)($dados['data_pagamento'] ?? date('Y-m-d')),
        ];

        try {
            $resultado = $service->baixar($id, $payload);
        } catch (RuntimeException $e) {
            throw new DashboardApiException($e->getMessage(), 400);
        }

        if (!($resultado['ok'] ?? false)) {
            throw new DashboardApiException((string)($resultado['erros'][0] ?? 'Nao foi possivel receber a conta.'), 400);
        }

        $atualizada = $repo->buscarPorId($id);

        return [
            'ok' => true,
            'mensagem' => 'Conta a receber baixada com sucesso.',
            'conta' => $this->normalizeContaReceber($atualizada ?: $conta),
        ];
    }

    private function listarContasReceberAbertas(int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                cr.*,
                cli.nome AS cliente_nome
            FROM contas_receber cr
            LEFT JOIN clientes cli ON cli.id = cr.cliente_id
            WHERE cr.status IN ('PENDENTE', 'VENCIDO')
              AND (cr.valor - COALESCE(cr.valor_pago, 0) - COALESCE(cr.desconto, 0)) > 0
            ORDER BY
                CASE WHEN cr.data_vencimento < CURRENT_DATE THEN 0 ELSE 1 END ASC,
                cr.data_vencimento ASC,
                cr.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'normalizeContaReceber'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function listarContasPagarAbertas(int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                cp.*,
                COALESCE(cli.nome, cp.fornecedor, 'Sem fornecedor') AS fornecedor_nome
            FROM contas_pagar cp
            LEFT JOIN clientes cli ON cli.id = cp.cliente_id
            WHERE cp.status IN ('PENDENTE', 'VENCIDO')
              AND (cp.valor - COALESCE(cp.valor_pago, 0) - COALESCE(cp.desconto, 0)) > 0
            ORDER BY
                CASE WHEN cp.data_vencimento < CURRENT_DATE THEN 0 ELSE 1 END ASC,
                cp.data_vencimento ASC,
                cp.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'normalizeContaPagar'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function normalizeContaReceber(array $item): array
    {
        $id = (int)($item['id'] ?? 0);

        return [
            'id' => $id,
            'cliente_nome' => (string)($item['cliente_nome'] ?? 'Sem cliente'),
            'descricao' => (string)($item['descricao'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'data_vencimento' => (string)($item['data_vencimento'] ?? ''),
            'valor' => round((float)($item['valor'] ?? 0), 2),
            'valor_pago' => round((float)($item['valor_pago'] ?? 0), 2),
            'desconto' => round((float)($item['desconto'] ?? 0), 2),
            'saldo_aberto' => round($this->saldoAbertoConta($item), 2),
            'atrasada' => $this->isContaAtrasada($item),
            'href' => $this->adminUrl('admin/financeiro/contas-receber.php?action=editar&id=' . $id),
            'acao_url' => $this->publicApiUrl('financeiro/conta/receber/' . $id),
        ];
    }

    private function normalizeContaPagar(array $item): array
    {
        $id = (int)($item['id'] ?? 0);

        return [
            'id' => $id,
            'fornecedor_nome' => (string)($item['fornecedor_nome'] ?? 'Sem fornecedor'),
            'descricao' => (string)($item['descricao'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'data_vencimento' => (string)($item['data_vencimento'] ?? ''),
            'valor' => round((float)($item['valor'] ?? 0), 2),
            'valor_pago' => round((float)($item['valor_pago'] ?? 0), 2),
            'desconto' => round((float)($item['desconto'] ?? 0), 2),
            'saldo_aberto' => round($this->saldoAbertoConta($item), 2),
            'atrasada' => $this->isContaAtrasada($item),
            'href' => $this->adminUrl('admin/financeiro/contas-pagar.php?action=editar&id=' . $id),
            'acao_url' => $this->publicApiUrl('financeiro/conta/pagar/' . $id),
        ];
    }

    private function dailyFluxoSparkline(string $metric): array
    {
        $start = new DateTimeImmutable('-6 days');
        $end = new DateTimeImmutable('today');

        $stmt = $this->pdo->prepare("
            WITH fluxo AS (
                {$this->fluxoBaseSql()}
            )
            SELECT
                fluxo.data_ref,
                COALESCE(SUM(CASE WHEN fluxo.tipo = 'receita' THEN fluxo.valor ELSE 0 END), 0) AS receitas,
                COALESCE(SUM(CASE WHEN fluxo.tipo = 'despesa' THEN fluxo.valor ELSE 0 END), 0) AS despesas
            FROM fluxo
            GROUP BY fluxo.data_ref
            ORDER BY fluxo.data_ref ASC
        ");
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d'),
            ':fim' => $end->format('Y-m-d'),
        ]);

        $indexed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $receitas = (float)($row['receitas'] ?? 0);
            $despesas = (float)($row['despesas'] ?? 0);
            $indexed[(string)$row['data_ref']] = match ($metric) {
                'receitas' => round($receitas, 2),
                'despesas' => round($despesas, 2),
                default => round($receitas - $despesas, 2),
            };
        }

        $values = [];
        foreach (new DatePeriod($start, new DateInterval('P1D'), 7) as $day) {
            $values[] = $indexed[$day->format('Y-m-d')] ?? 0.0;
        }

        return $values;
    }

    private function inadimplenciaSparkline(): array
    {
        $start = new DateTimeImmutable('-6 days');
        $values = [];
        foreach (new DatePeriod($start, new DateInterval('P1D'), 7) as $day) {
            $values[] = round($this->inadimplenciaEmAberto($day->format('Y-m-d')), 2);
        }

        return $values;
    }

    private function inadimplenciaEmAberto(?string $referenceDate = null): float
    {
        $referenceDate = $referenceDate ?? date('Y-m-d');

        return round((float)$this->scalar("
            SELECT COALESCE(SUM(valor - COALESCE(valor_pago, 0) - COALESCE(desconto, 0)), 0)
            FROM contas_receber
            WHERE status IN ('PENDENTE', 'VENCIDO')
              AND data_vencimento < CAST(:referencia AS date)
        ", [':referencia' => $referenceDate]), 2);
    }

    private function fluxoResumo(string $start, string $end): array
    {
        // Fonte unica: movimentacoes afeta_saldo=TRUE.
        // Antes este metodo somava direto de contas_receber.valor_pago, duplicando
        // com a movimentacao gerada pela baixa.
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE 0 END), 0) AS receitas,
                COALESCE(SUM(CASE WHEN tipo = 'Saida'   THEN valor ELSE 0 END), 0) AS despesas
              FROM movimentacoes
             WHERE afeta_saldo = TRUE
               AND data_movimentacao >= CAST(:inicio AS date)
               AND data_movimentacao < (CAST(:fim AS date) + INTERVAL '1 day')
        ");
        $stmt->execute([
            ':inicio' => $start,
            ':fim' => $end,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['receitas' => 0, 'despesas' => 0];

        $receitas = round((float)($row['receitas'] ?? 0), 2);
        $despesas = round((float)($row['despesas'] ?? 0), 2);

        return [
            'receitas' => $receitas,
            'despesas' => $despesas,
            'lucro' => round($receitas - $despesas, 2),
        ];
    }

    private function fluxoBaseSql(): string
    {
        return "
            SELECT
                (cr.data_pagamento AT TIME ZONE 'America/Sao_Paulo')::date AS data_ref,
                COALESCE(cr.valor_pago, 0) AS valor,
                'receita' AS tipo,
                cr.categoria_dre_id
            FROM contas_receber cr
            WHERE cr.status IN ('PAGO', 'PARCIALMENTE_PAGO')
              AND cr.data_pagamento IS NOT NULL
              AND (cr.data_pagamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN CAST(:inicio AS date) AND CAST(:fim AS date)

            UNION ALL

            SELECT
                (cp.data_pagamento AT TIME ZONE 'America/Sao_Paulo')::date AS data_ref,
                COALESCE(cp.valor_pago, 0) AS valor,
                'despesa' AS tipo,
                cp.categoria_dre_id
            FROM contas_pagar cp
            WHERE cp.status IN ('Pago', 'Parcialmente Pago', 'PAGO', 'PARCIALMENTE_PAGO')
              AND cp.data_pagamento IS NOT NULL
              AND (cp.data_pagamento AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN CAST(:inicio AS date) AND CAST(:fim AS date)

            UNION ALL

            SELECT
                (m.data_movimentacao AT TIME ZONE 'America/Sao_Paulo')::date AS data_ref,
                COALESCE(m.valor, 0) AS valor,
                CASE WHEN m.tipo = 'Entrada' THEN 'receita' ELSE 'despesa' END AS tipo,
                m.categoria_dre_id
            FROM movimentacoes m
            WHERE m.categoria_dre_id IS NOT NULL
              AND m.conta_receber_id IS NULL
              AND m.conta_pagar_id IS NULL
              AND (m.data_movimentacao AT TIME ZONE 'America/Sao_Paulo')::date BETWEEN CAST(:inicio AS date) AND CAST(:fim AS date)
        ";
    }

    private function saldoAbertoConta(array $conta): float
    {
        return max(
            0.0,
            round(
                (float)($conta['valor'] ?? 0)
                - (float)($conta['valor_pago'] ?? 0)
                - (float)($conta['desconto'] ?? 0),
                2
            )
        );
    }

    private function isContaAtrasada(array $conta): bool
    {
        $vencimento = (string)($conta['data_vencimento'] ?? '');
        if ($vencimento === '') {
            return false;
        }

        return $vencimento < date('Y-m-d') && $this->saldoAbertoConta($conta) > 0;
    }

    private function adminUrl(string $path): string
    {
        if (function_exists('tenantUrl')) {
            return \tenantUrl($path);
        }

        return '/sistema_dm/public/' . ltrim($path, '/');
    }

    private function publicApiUrl(string $path): string
    {
        if (function_exists('tenantUrl')) {
            $full = \tenantUrl($path);
            return str_replace('/public/', '/', $full);
        }

        return '/sistema_dm/' . ltrim($path, '/');
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

    private function monthLabel(int $month): string
    {
        $labels = [
            1 => 'Jan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'Mai',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Set',
            10 => 'Out',
            11 => 'Nov',
            12 => 'Dez',
        ];

        return $labels[$month] ?? '--';
    }
}
