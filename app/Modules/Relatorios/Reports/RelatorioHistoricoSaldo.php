<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioHistoricoSaldo extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Extrato Detalhado da Conta';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'extrato_conta_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $conta_id = $filtros['conta_id']    ?? '';
        $ini      = $filtros['data_inicio'] ?? date('Y-m-01');
        $fim      = $filtros['data_fim']    ?? date('Y-m-d');

        $this->normalizarDatas($ini, $fim);

        if (empty($conta_id)) {
            return $this->emptyResult();
        }

        // Informações da conta
        $stmt = $this->pdo->prepare('SELECT id, nome, tipo, saldo_inicial FROM contas WHERE id = :id');
        $stmt->execute([':id' => $conta_id]);
        $conta_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Saldo anterior ao período
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE -valor END), 0) AS saldo_anterior
            FROM movimentacoes
            WHERE conta_id = :id AND data_movimentacao < :ini
        ");
        $stmt->execute([':id' => $conta_id, ':ini' => $ini]);
        $saldo_anterior = (float)($stmt->fetchColumn() ?? 0);
        $saldo_inicial  = (float)($conta_info['saldo_inicial'] ?? 0) + $saldo_anterior;

        // Movimentações do período
        $stmt = $this->pdo->prepare("
            SELECT data_movimentacao, descricao, tipo, valor
            FROM movimentacoes
            WHERE conta_id = :id
              AND DATE(data_movimentacao) BETWEEN :ini AND :fim
            ORDER BY data_movimentacao ASC, created_at ASC
        ");
        $stmt->execute([':id' => $conta_id, ':ini' => $ini, ':fim' => $fim]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $movimentacoes  = [];
        $saldo_acumulado = $saldo_inicial;
        $total_entradas  = 0.0;
        $total_saidas    = 0.0;

        foreach ($rows as $r) {
            $valor = (float)$r['valor'];
            if ($r['tipo'] === 'Entrada') {
                $saldo_acumulado += $valor;
                $total_entradas  += $valor;
            } else {
                $saldo_acumulado -= $valor;
                $total_saidas    += $valor;
            }
            $movimentacoes[] = [
                'data_movimentacao' => $r['data_movimentacao'],
                'descricao'         => $r['descricao'],
                'tipo'              => $r['tipo'],
                'valor'             => $valor,
                'saldo_acumulado'   => $saldo_acumulado,
            ];
        }

        return [
            'conta_info'    => $conta_info,
            'saldos'        => [
                'saldo_inicial'   => $saldo_inicial,
                'total_entradas'  => $total_entradas,
                'total_saidas'    => $total_saidas,
                'saldo_final'     => $saldo_acumulado,
            ],
            'movimentacoes' => $movimentacoes,
            'data_inicio'   => $ini,
            'data_fim'      => $fim,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'conta_info'    => [],
            'saldos'        => ['saldo_inicial' => 0, 'total_entradas' => 0, 'total_saidas' => 0, 'saldo_final' => 0],
            'movimentacoes' => [],
            'data_inicio'   => '',
            'data_fim'      => '',
        ];
    }

    public function buscarContas(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, tipo FROM contas WHERE ativo = TRUE ORDER BY nome');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PDF
    // =========================================================================

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $conta      = $dados['conta_info'];
        $saldos     = $dados['saldos'];
        $mov        = $dados['movimentacoes'];
        $nomeConta  = htmlspecialchars($conta['nome'] ?? '—');

        $html  = '<p style="font-size:9pt;margin-bottom:8px;">Conta: <strong>' . $nomeConta . '</strong></p>';

        $html .= $this->htmlResumo([
            ['label' => 'Saldo Inicial',    'valor' => $this->brl($saldos['saldo_inicial'])],
            ['label' => 'Total Entradas',   'valor' => $this->brl($saldos['total_entradas']), 'class' => 'positivo'],
            ['label' => 'Total Saídas',     'valor' => $this->brl($saldos['total_saidas']),   'class' => 'negativo'],
            ['label' => 'Saldo Final',      'valor' => $this->brl($saldos['saldo_final'])],
        ]);

        $colunas = [
            ['label' => 'Data',                   'align' => 'center', 'width' => '80px'],
            ['label' => 'Histórico/Descrição',    'align' => 'left'],
            ['label' => 'Entrada (Crédito)',       'align' => 'right',  'width' => '110px'],
            ['label' => 'Saída (Débito)',          'align' => 'right',  'width' => '110px'],
            ['label' => 'Saldo Acumulado',         'align' => 'right',  'width' => '110px'],
        ];

        $linhas = [];
        foreach ($mov as $m) {
            $isEntrada = $m['tipo'] === 'Entrada';
            $linhas[]  = [
                'cells' => [
                    $this->data($m['data_movimentacao']),
                    htmlspecialchars($m['descricao']),
                    $isEntrada ? '<span class="positivo">' . $this->brl($m['valor']) . '</span>' : '',
                    !$isEntrada ? '<span class="negativo">' . $this->brl($m['valor']) . '</span>' : '',
                    '<strong>' . $this->brl($m['saldo_acumulado']) . '</strong>',
                ],
            ];
        }

        $html .= $this->htmlTabela($colunas, $linhas);
        return $html;
    }

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = parent::buildPdfOpcoes($filtros);
        unset($opcoes['filtros']);
        return $opcoes;
    }
}
