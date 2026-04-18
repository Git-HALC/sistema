<?php
declare(strict_types=1);

namespace App\Modules\Financeiro;

use PDO;

final class FluxoCaixaProjetadoRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function saldoAtualContasAtivas(): float
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(saldo_atual), 0) FROM contas WHERE ativo = TRUE"
        );
        return (float)$stmt->fetchColumn();
    }

    /**
     * Entradas previstas (contas_receber PENDENTE) por data_vencimento.
     * @return array<int, array{data:string, valor:float, descricao:string, cliente:?string}>
     */
    public function entradasPrevistas(string $dataInicio, string $dataFim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cr.data_vencimento::date AS data,
                    cr.valor::float8 AS valor,
                    cr.descricao,
                    c.nome AS cliente
               FROM contas_receber cr
               LEFT JOIN clientes c ON c.id = cr.cliente_id
              WHERE cr.status IN ('PENDENTE','VENCIDO')
                AND cr.data_vencimento BETWEEN :ini AND :fim
              ORDER BY cr.data_vencimento, cr.id"
        );
        $stmt->execute([':ini' => $dataInicio, ':fim' => $dataFim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Saidas previstas (contas_pagar PENDENTE) por data_vencimento.
     * @return array<int, array{data:string, valor:float, descricao:string, fornecedor:?string}>
     */
    public function saidasPrevistas(string $dataInicio, string $dataFim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cp.data_vencimento::date AS data,
                    cp.valor::float8 AS valor,
                    cp.descricao,
                    COALESCE(c.nome, cp.fornecedor) AS fornecedor
               FROM contas_pagar cp
               LEFT JOIN clientes c ON c.id = cp.cliente_id
              WHERE cp.status IN ('PENDENTE','VENCIDO')
                AND cp.data_vencimento BETWEEN :ini AND :fim
              ORDER BY cp.data_vencimento, cp.id"
        );
        $stmt->execute([':ini' => $dataInicio, ':fim' => $dataFim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
