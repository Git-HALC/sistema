<?php

namespace App\Modules\Orcamento;

/**
 * Model: OrcamentoItem
 *
 * Representa um item (linha) de um orçamento.
 * O subtotal é calculado localmente e armazenado no banco
 * para consultas diretas sem recalcular.
 */
class OrcamentoItem
{
    public const TIPO_PRODUTO = 'PRODUTO';
    public const TIPO_SERVICO = 'SERVICO';

    public int     $id              = 0;
    public int     $orcamentoId     = 0;
    public string  $tipoItem        = self::TIPO_PRODUTO;
    public ?int    $produtoId       = null;
    public ?int    $servicoId       = null;
    public string  $nomeProduto     = '';
    public ?string $nomeServico     = null;
    public ?string $descricaoItem   = null;
    public float   $quantidade      = 1.0;
    public float   $precoUnitario   = 0.0;
    public float   $subtotal        = 0.0;  // Maps to valor_total_item
    public string  $createdAt       = '';
    public string  $updatedAt       = '';

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function fromArray(array $row): self
    {
        $i                    = new self();
        $i->id                = (int)   ($row['id'] ?? 0);
        $i->orcamentoId       = (int)   ($row['orcamento_id'] ?? 0);
        $i->tipoItem          = (string)($row['tipo_item'] ?? self::TIPO_PRODUTO);
        $i->produtoId         = isset($row['produto_id']) ? (int) $row['produto_id'] : null;
        $i->servicoId         = isset($row['servico_id']) ? (int) $row['servico_id'] : null;
        $i->nomeProduto       = (string) ($row['nome_produto'] ?? '');
        $i->nomeServico       = isset($row['nome_servico']) && $row['nome_servico'] !== '' ? (string)$row['nome_servico'] : null;
        $i->descricaoItem     = $row['descricao_item'] ?? null;
        $i->quantidade        = (float)  ($row['quantidade'] ?? 1.0);
        $i->precoUnitario     = (float)  ($row['valor_unitario'] ?? 0.0);
        $i->subtotal          = (float)  ($row['valor_total_item'] ?? 0.0);
        $i->createdAt         = $row['created_at'] ?? '';
        $i->updatedAt         = $row['updated_at'] ?? '';

        return $i;
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'orcamento_id'      => $this->orcamentoId,
            'tipo_item'         => $this->tipoItem,
            'produto_id'        => $this->produtoId,
            'servico_id'        => $this->servicoId,
            'nome_produto'      => $this->nomeProduto,
            'nome_servico'      => $this->nomeServico,
            'descricao_item'    => $this->descricaoItem,
            'quantidade'        => $this->quantidade,
            'valor_unitario'    => $this->precoUnitario,
            'valor_total_item'  => $this->subtotal,
            'created_at'        => $this->createdAt,
            'updated_at'        => $this->updatedAt,
        ];
    }

    // -------------------------------------------------------------------------
    // Cálculo
    // -------------------------------------------------------------------------

    /**
     * subtotal = quantidade × precoUnitario × (1 − desconto/100)
     * Atualiza $this->subtotal e retorna o valor.
     */
    public function calcularSubtotal(): float
    {
        $this->subtotal = round(
            $this->quantidade * $this->precoUnitario,
            4
        );
        return $this->subtotal;
    }

    // -------------------------------------------------------------------------
    // Helpers de exibição
    // -------------------------------------------------------------------------

    public function subtotalFormatado(): string
    {
        return 'R$ ' . number_format($this->subtotal, 2, ',', '.');
    }

    public function precoUnitarioFormatado(): string
    {
        return number_format($this->precoUnitario, 2, ',', '.');
    }

    public function nomeItem(): string
    {
        return $this->tipoItem === self::TIPO_SERVICO
            ? (string)($this->nomeServico ?? '')
            : $this->nomeProduto;
    }
}
