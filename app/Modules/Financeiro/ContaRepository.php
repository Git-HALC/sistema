<?php

namespace App\Modules\Financeiro;

use PDO;

class ContaRepository
{
    private const TABLE = 'contas';

    public function __construct(private readonly PDO $pdo) {}

    public function listar(bool $apenasAtivas = false): array
    {
        $sql = 'SELECT * FROM ' . self::TABLE;
        if ($apenasAtivas) {
            $sql .= ' WHERE ativo = TRUE';
        }
        $sql .= ' ORDER BY nome ASC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?Conta
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Conta::fromArray($row) : null;
    }

    public function criar(Conta $conta): int
    {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare('
            INSERT INTO ' . self::TABLE . '
                (nome, tipo, banco, agencia, numero_conta, saldo_inicial, ativo, created_at)
            VALUES
                (:nome, :tipo, :banco, :agencia, :numero_conta, :saldo_inicial, :ativo, :created_at)
        ');
        $stmt->execute([
            ':nome' => $conta->nome,
            ':tipo' => $conta->tipo,
            ':banco' => $conta->banco,
            ':agencia' => $conta->agencia,
            ':numero_conta' => $conta->numeroConta,
            ':saldo_inicial' => $conta->saldoInicial,
            ':ativo' => $conta->ativo,
            ':created_at' => ($conta->dataSaldoInicial ?: date('Y-m-d')) . ' 00:00:00',
        ]);

        $id = (int) $this->pdo->lastInsertId(self::TABLE . '_id_seq');

        // Inicializar saldo_atual igual ao saldo_inicial
        $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET saldo_atual = saldo_inicial WHERE id = :id')
            ->execute([':id' => $id]);

        $this->pdo->commit();
        return $id;
    }

    public function atualizar(Conta $conta): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE ' . self::TABLE . ' SET
                nome         = :nome,
                tipo         = :tipo,
                banco        = :banco,
                agencia      = :agencia,
                numero_conta = :numero_conta,
                saldo_inicial= :saldo_inicial,
                ativo        = :ativo,
                updated_at   = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $conta->id,
            ':nome' => $conta->nome,
            ':tipo' => $conta->tipo,
            ':banco' => $conta->banco,
            ':agencia' => $conta->agencia,
            ':numero_conta' => $conta->numeroConta,
            ':saldo_inicial' => $conta->saldoInicial,
            ':ativo' => $conta->ativo,
        ]);

        // Recalcular saldo_atual baseado nas movimentações
        $this->recalcularSaldoAtual($conta->id);
        return true;
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM movimentacoes WHERE conta_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET ativo = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([':id' => $id]);
            return true;
        }

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function atualizarSaldo(int $id, float $valor, string $tipo): bool
    {
        $op = $tipo === 'Entrada' ? '+' : '-';
        $stmt = $this->pdo->prepare('UPDATE ' . self::TABLE . " SET saldo_atual = saldo_atual {$op} :valor WHERE id = :id");
        $stmt->execute([':id' => $id, ':valor' => $valor]);
        return true;
    }

    public function recalcularSaldoAtual(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT saldo_inicial FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $total = $this->somaMovimentacoes($id);

        $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET saldo_atual = :saldo WHERE id = :id')
            ->execute([':saldo' => (float)$row['saldo_inicial'] + $total, ':id' => $id]);
        return true;
    }

    private function recalcularSaldoPorSaldoInicial(int $id, float $novoSaldoInicial): void
    {
        $total = $this->somaMovimentacoes($id);

        $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET saldo_atual = :saldo WHERE id = :id')
            ->execute([':saldo' => $novoSaldoInicial + $total, ':id' => $id]);
    }

    private function somaMovimentacoes(int $id): float
    {
        $stmt = $this->pdo->prepare("\n            SELECT SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE -valor END) AS total\n            FROM movimentacoes\n            WHERE conta_id = :id\n        ");
        $stmt->execute([':id' => $id]);
        return (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
}
