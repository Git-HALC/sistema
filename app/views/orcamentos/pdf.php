<?php
/** @var \App\Modules\Orcamento\Orcamento $orcamento */
/** @var \App\Modules\Orcamento\OrcamentoItem[] $itens */
/** @var string $logoHtml */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Orçamento <?= htmlspecialchars($orcamento->codigo ?? "ORC-{$orcamento->id}") ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 18px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #2563eb; padding-bottom: 12px; }
        .logo { max-height: 56px; margin-bottom: 8px; }
        .title { font-size: 21px; font-weight: 700; color: #1d4ed8; margin: 0; }
        .subtitle { margin-top: 4px; color: #4b5563; font-size: 12px; }
        .meta { width: 100%; border-collapse: collapse; margin: 14px 0 18px; }
        .meta th { width: 24%; background: #eff6ff; color: #1e40af; text-align: left; padding: 8px; border: 1px solid #dbeafe; }
        .meta td { padding: 8px; border: 1px solid #e5e7eb; }
        .items { width: 100%; border-collapse: collapse; }
        .items th { background: #2563eb; color: #fff; padding: 8px; border: 1px solid #1d4ed8; text-align: left; }
        .items td { border: 1px solid #e5e7eb; padding: 7px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row td { background: #f8fafc; font-weight: 700; }
        .obs { margin-top: 12px; padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #6b7280; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .badge-aberto { background: #dbeafe; color: #1e40af; }
        .badge-aprovado { background: #dcfce7; color: #166534; }
        .badge-cancelado { background: #e5e7eb; color: #374151; }
    </style>
</head>
<body>
    <div class="header">
        <?= $logoHtml ?>
        <p class="title">Orçamento <?= htmlspecialchars($orcamento->codigo ?? "ORC-{$orcamento->id}") ?></p>
        <p class="subtitle">Emitido em <?= $orcamento->dataEmissaoFormatada() ?> • Gerado em <?= date('d/m/Y H:i:s') ?></p>
    </div>

    <?php
    $badgeClass = match ($orcamento->status) {
        \App\Modules\Orcamento\Orcamento::STATUS_APROVADO => 'badge-aprovado',
        \App\Modules\Orcamento\Orcamento::STATUS_CANCELADO => 'badge-cancelado',
        default => 'badge-aberto',
    };
    ?>

    <table class="meta">
        <tr>
            <th>Código</th>
            <td><?= htmlspecialchars($orcamento->codigo ?? "ORC-{$orcamento->id}") ?></td>
            <th>Status</th>
            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($orcamento->statusLabel()) ?></span></td>
        </tr>
        <tr>
            <th>Cliente</th>
            <td><?= htmlspecialchars($orcamento->clienteNome ?? '— Sem cliente —') ?></td>
            <th>Validade</th>
            <td><?= $orcamento->dataValidadeFormatada() ?></td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:6%">#</th>
                <th style="width:42%">Item</th>
                <th style="width:12%" class="text-center">Un.</th>
                <th style="width:13%" class="text-right">Qtd</th>
                <th style="width:13%" class="text-right">Valor Unit.</th>
                <th style="width:14%" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($itens)): ?>
            <tr>
                <td colspan="6" class="text-center">Nenhum item cadastrado.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($itens as $index => $item): ?>
                <tr>
                    <td class="text-center"><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars((string)($item->nomeItem() !== '' ? $item->nomeItem() : '—')) ?></td>
                    <td class="text-center"><?= htmlspecialchars($item->unidade ?? 'UN') ?></td>
                    <td class="text-right"><?= number_format((float)$item->quantidade, 2, ',', '.') ?></td>
                    <td class="text-right">R$ <?= number_format((float)$item->precoUnitario, 2, ',', '.') ?></td>
                    <td class="text-right">R$ <?= number_format((float)$item->subtotal, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">Total do Orçamento</td>
                <td class="text-right"><?= $orcamento->valorTotalFormatado() ?></td>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($orcamento->observacoes)): ?>
        <div class="obs">
            <strong>Observações:</strong><br>
            <?= nl2br(htmlspecialchars($orcamento->observacoes)) ?>
        </div>
    <?php endif; ?>

    <div class="footer">
        Documento gerado automaticamente pelo Sistema DM
    </div>
</body>
</html>