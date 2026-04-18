<?php

namespace App\Modules\Servicos;

use DateTime;

class Servico
{
    public const STATUS_PENDENTE = 'PENDENTE';
    public const STATUS_EM_PROCESSO = 'EM_PROCESSO';
    public const STATUS_CONCLUIDO = 'CONCLUIDO';
    public const STATUS_FATURADO = 'FATURADO';
    public const STATUS_CANCELADO = 'CANCELADO';

    public const STATUS_VALIDOS = [
        self::STATUS_PENDENTE,
        self::STATUS_EM_PROCESSO,
        self::STATUS_CONCLUIDO,
        self::STATUS_FATURADO,
        self::STATUS_CANCELADO,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDENTE => 'Pendente',
        self::STATUS_EM_PROCESSO => 'Em Processo',
        self::STATUS_CONCLUIDO => 'Concluido',
        self::STATUS_FATURADO => 'Faturado',
        self::STATUS_CANCELADO => 'Cancelado',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDENTE => 'bg-warning',
        self::STATUS_EM_PROCESSO => 'bg-info',
        self::STATUS_CONCLUIDO => 'bg-dark',
        self::STATUS_FATURADO => 'bg-primary',
        self::STATUS_CANCELADO => 'bg-danger',
    ];

    public string $id = '';
    public ?int $numero = null;
    public ?int $clienteId = null;
    public ?int $usuarioId = null;
    public ?int $orcamentoId = null;
    public ?int $servicoCatalogoId = null;
    public ?int $produtoId = null;
    public float $produtoQuantidade = 0.0;
    public float $servicoValor = 0.0;
    public float $produtoValorUnitario = 0.0;
    public ?string $descontoTipo = null;
    public float $descontoValor = 0.0;
    public string $dataServico = '';
    public string $status = self::STATUS_PENDENTE;
    public string $nomeCliente = '';
    public ?string $telefoneCliente = null;
    public string $servicoNome = '';
    public ?string $produtoNome = null;
    public ?string $placa = null;
    public ?string $modeloVeiculo = null;
    public float $valorTotal = 0.0;
    public ?string $observacoes = null;
    public ?string $dataFaturamento = null;
    public bool $ativo = true;
    public string $createdAt = '';
    public string $updatedAt = '';
    /** @var ServicoItem[] */
    public array $itens = [];

    public static function fromArray(array $row): self
    {
        $s = new self();
        $s->id = (string)($row['id'] ?? '');
        $s->numero = isset($row['numero']) ? (int)$row['numero'] : null;
        $s->clienteId = isset($row['cliente_id']) && $row['cliente_id'] !== '' ? (int)$row['cliente_id'] : null;
        $s->usuarioId = isset($row['usuario_id']) && $row['usuario_id'] !== '' ? (int)$row['usuario_id'] : null;
        $s->orcamentoId = isset($row['orcamento_id']) && $row['orcamento_id'] !== '' ? (int)$row['orcamento_id'] : null;
        $s->servicoCatalogoId = isset($row['servico_catalogo_id']) && $row['servico_catalogo_id'] !== '' ? (int)$row['servico_catalogo_id'] : null;
        $s->produtoId = isset($row['produto_id']) && $row['produto_id'] !== '' ? (int)$row['produto_id'] : null;
        $s->produtoQuantidade = isset($row['produto_quantidade']) ? (float)$row['produto_quantidade'] : 0.0;
        $s->servicoValor = isset($row['servico_valor']) ? (float)$row['servico_valor'] : 0.0;
        $s->produtoValorUnitario = isset($row['produto_valor_unitario']) ? (float)$row['produto_valor_unitario'] : 0.0;
        $s->descontoTipo = isset($row['desconto_tipo']) && $row['desconto_tipo'] !== '' ? strtoupper((string)$row['desconto_tipo']) : null;
        $s->descontoValor = isset($row['desconto_valor']) ? (float)$row['desconto_valor'] : 0.0;
        $s->dataServico = (string)($row['data_servico'] ?? '');
        $s->status = strtoupper((string)($row['status'] ?? self::STATUS_PENDENTE));
        $s->nomeCliente = (string)($row['nome_cliente'] ?? '');
        $s->telefoneCliente = isset($row['telefone_cliente']) && $row['telefone_cliente'] !== '' ? (string)$row['telefone_cliente'] : null;
        $s->servicoNome = (string)($row['servico_nome'] ?? '');
        $s->produtoNome = isset($row['produto_nome']) && $row['produto_nome'] !== '' ? (string)$row['produto_nome'] : null;
        $s->placa = isset($row['placa']) && $row['placa'] !== '' ? (string)$row['placa'] : null;
        $s->modeloVeiculo = isset($row['modelo_veiculo']) && $row['modelo_veiculo'] !== '' ? (string)$row['modelo_veiculo'] : null;
        $s->valorTotal = (float)($row['valor_total'] ?? 0);
        $s->observacoes = isset($row['observacoes']) && $row['observacoes'] !== '' ? (string)$row['observacoes'] : null;
        $s->dataFaturamento = isset($row['data_faturamento']) && $row['data_faturamento'] !== '' ? (string)$row['data_faturamento'] : null;
        $s->ativo = filter_var($row['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $s->createdAt = (string)($row['created_at'] ?? '');
        $s->updatedAt = (string)($row['updated_at'] ?? '');

        return $s;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'cliente_id' => $this->clienteId,
            'usuario_id' => $this->usuarioId,
            'orcamento_id' => $this->orcamentoId,
            'servico_catalogo_id' => $this->servicoCatalogoId,
            'produto_id' => $this->produtoId,
            'produto_quantidade' => $this->produtoQuantidade,
            'servico_valor' => $this->servicoValor,
            'produto_valor_unitario' => $this->produtoValorUnitario,
            'desconto_tipo' => $this->descontoTipo,
            'desconto_valor' => $this->descontoValor,
            'data_servico' => $this->dataServico,
            'status' => $this->status,
            'nome_cliente' => $this->nomeCliente,
            'telefone_cliente' => $this->telefoneCliente,
            'servico_nome' => $this->servicoNome,
            'produto_nome' => $this->produtoNome,
            'placa' => $this->placa,
            'modelo_veiculo' => $this->modeloVeiculo,
            'valor_total' => $this->valorTotal,
            'observacoes' => $this->observacoes,
            'data_faturamento' => $this->dataFaturamento,
            'ativo' => $this->ativo,
        ];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary';
    }

    public function dataServicoFormatada(): string
    {
        if ($this->dataServico === '') {
            return '';
        }

        try {
            return (new DateTime($this->dataServico))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $this->dataServico;
        }
    }

    public function produtoQuantidadeFormatada(int $casasDecimais = 2): string
    {
        return number_format($this->produtoQuantidade, $casasDecimais, ',', '.');
    }
}
