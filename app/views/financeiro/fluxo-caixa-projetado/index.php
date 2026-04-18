<?php
/**
 * @var array $projecao
 * @var string $agrupamento
 */
$baseUrl = tenantUrl('admin/financeiro/fluxo-caixa-projetado.php');
$brl = static fn ($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Fluxo de Caixa Projetado</h1>
            <p class="text-muted mb-0 small">
                Saldo atual <strong class="text-primary"><?= $brl($projecao['saldo_atual']) ?></strong>
                · Projeção baseada em contas a receber/pagar pendentes
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
                <i class="fas fa-print me-1"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Abas de agrupamento -->
    <ul class="nav nav-tabs mb-3 d-print-none">
        <?php foreach ([
            'diario'  => ['label' => '7 dias',   'icon' => 'fa-calendar-day'],
            'semanal' => ['label' => '4 semanas', 'icon' => 'fa-calendar-week'],
            'mensal'  => ['label' => '3 meses',   'icon' => 'fa-calendar-alt'],
        ] as $key => $meta): ?>
            <li class="nav-item">
                <a class="nav-link <?= $agrupamento === $key ? 'active' : '' ?>"
                   href="<?= htmlspecialchars($baseUrl . '?agrupamento=' . $key) ?>">
                    <i class="fas <?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Período</th>
                            <th class="text-end">Entradas</th>
                            <th class="text-end">Saídas</th>
                            <th class="text-end">Líquido</th>
                            <th class="text-end">Saldo Projetado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projecao['periodos'] as $idx => $p):
                            $liquido = $p['entradas'] - $p['saidas'];
                            $saldoNeg = $p['saldo_projetado'] < 0;
                        ?>
                            <tr class="<?= $saldoNeg ? 'table-danger' : '' ?>">
                                <td>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-start"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#det-<?= $idx ?>"
                                            aria-expanded="false">
                                        <i class="fas fa-chevron-right me-2"></i>
                                        <strong><?= htmlspecialchars($p['label']) ?></strong>
                                    </button>
                                </td>
                                <td class="text-end text-success">+<?= $brl($p['entradas']) ?></td>
                                <td class="text-end text-danger">-<?= $brl($p['saidas']) ?></td>
                                <td class="text-end fw-semibold <?= $liquido >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= ($liquido >= 0 ? '+' : '') . $brl($liquido) ?>
                                </td>
                                <td class="text-end fw-bold <?= $saldoNeg ? 'text-danger' : '' ?>">
                                    <?= $brl($p['saldo_projetado']) ?>
                                    <?php if ($saldoNeg): ?>
                                        <i class="fas fa-triangle-exclamation text-danger ms-1" title="Saldo negativo projetado"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="collapse" id="det-<?= $idx ?>">
                                <td colspan="5" class="bg-light">
                                    <div class="row g-3 p-3">
                                        <div class="col-md-6">
                                            <h6 class="small text-muted text-uppercase mb-2">
                                                <i class="fas fa-arrow-down text-success me-1"></i>Entradas (<?= count($p['entradas_det']) ?>)
                                            </h6>
                                            <?php if (empty($p['entradas_det'])): ?>
                                                <p class="text-muted small mb-0">Nenhuma entrada prevista.</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0">
                                                    <tbody>
                                                        <?php foreach ($p['entradas_det'] as $e): ?>
                                                            <tr>
                                                                <td class="small"><?= date('d/m', strtotime($e['data'])) ?></td>
                                                                <td class="small">
                                                                    <?= htmlspecialchars((string)$e['descricao']) ?>
                                                                    <?php if (!empty($e['cliente'])): ?>
                                                                        <br><span class="text-muted"><?= htmlspecialchars($e['cliente']) ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end text-success fw-semibold"><?= $brl($e['valor']) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="small text-muted text-uppercase mb-2">
                                                <i class="fas fa-arrow-up text-danger me-1"></i>Saídas (<?= count($p['saidas_det']) ?>)
                                            </h6>
                                            <?php if (empty($p['saidas_det'])): ?>
                                                <p class="text-muted small mb-0">Nenhuma saída prevista.</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0">
                                                    <tbody>
                                                        <?php foreach ($p['saidas_det'] as $s): ?>
                                                            <tr>
                                                                <td class="small"><?= date('d/m', strtotime($s['data'])) ?></td>
                                                                <td class="small">
                                                                    <?= htmlspecialchars((string)$s['descricao']) ?>
                                                                    <?php if (!empty($s['fornecedor'])): ?>
                                                                        <br><span class="text-muted"><?= htmlspecialchars($s['fornecedor']) ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end text-danger fw-semibold"><?= $brl($s['valor']) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info small d-print-none">
        <i class="fas fa-info-circle me-1"></i>
        A projeção considera apenas contas com status <strong>PENDENTE</strong> ou <strong>VENCIDO</strong>.
        Dias com saldo negativo aparecem destacados em vermelho.
    </div>
</div>
