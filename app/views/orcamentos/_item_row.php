<?php
/**
 * Partial: _item_row.php
 * Variáveis esperadas: $item (OrcamentoItem), $idx (int)
 */
$tipoItem = strtoupper((string)($item->tipoItem ?? \App\Modules\Orcamento\OrcamentoItem::TIPO_PRODUTO));
?>
<tr class="item-row">
    <td>
        <select name="itens[<?= $idx ?>][tipo_item]" class="form-select form-select-sm tipo-item-select mb-2">
            <option value="PRODUTO" <?= $tipoItem === 'PRODUTO' ? 'selected' : '' ?>>Produto</option>
            <option value="SERVICO" <?= $tipoItem === 'SERVICO' ? 'selected' : '' ?>>Serviço</option>
        </select>

        <select name="itens[<?= $idx ?>][produto_id]" class="form-select form-select-sm produto-select mb-2" <?= $tipoItem === 'PRODUTO' ? 'required' : '' ?> style="display: <?= $tipoItem === 'PRODUTO' ? 'block' : 'none' ?>;">
            <option value="">Selecione um produto...</option>
            <?php foreach (($produtos ?? []) as $p): ?>
                <option value="<?= (int)$p['id'] ?>"
                        data-preco="<?= htmlspecialchars((string)$p['preco_venda']) ?>"
                        data-nome="<?= htmlspecialchars((string)$p['nome']) ?>"
                    <?= (int)($item->produtoId ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$p['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="itens[<?= $idx ?>][servico_id]" class="form-select form-select-sm servico-select mb-2" <?= $tipoItem === 'SERVICO' ? 'required' : '' ?> style="display: <?= $tipoItem === 'SERVICO' ? 'block' : 'none' ?>;">
            <option value="">Selecione um serviço...</option>
            <?php foreach (($servicosCatalogo ?? []) as $s): ?>
                <option value="<?= (int)$s['id'] ?>"
                        data-preco="<?= htmlspecialchars((string)$s['valor_base']) ?>"
                        data-nome="<?= htmlspecialchars((string)$s['nome']) ?>"
                    <?= (int)($item->servicoId ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$s['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="hidden" name="itens[<?= $idx ?>][nome_produto]" value="<?= htmlspecialchars((string)$item->nomeProduto) ?>" class="campo-nome-produto">
        <input type="hidden" name="itens[<?= $idx ?>][nome_servico]" value="<?= htmlspecialchars((string)($item->nomeServico ?? '')) ?>" class="campo-nome-servico">
        <input type="hidden" name="itens[<?= $idx ?>][descricao_item]" value="<?= htmlspecialchars((string)($item->descricaoItem ?? '')) ?>" class="campo-descricao">
    </td>
    <td>
        <input type="number" name="itens[<?= $idx ?>][quantidade]"
               value="<?= $item->quantidade ?>" min="0.0001" step="0.0001"
               class="form-control form-control-sm text-end campo-calc" required>
    </td>
    <td>
        <input type="number" name="itens[<?= $idx ?>][preco_unitario]"
               value="<?= $item->precoUnitario ?>" min="0" step="0.01"
               class="form-control form-control-sm text-end campo-calc">
    </td>
    <td class="text-end align-middle subtotal-cell fw-semibold">
        <?= $item->subtotalFormatado() ?>
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-outline-danger btn-sm"
                onclick="removerItem(this)" title="Remover">
            <i class="fas fa-times"></i>
        </button>
    </td>
</tr>
