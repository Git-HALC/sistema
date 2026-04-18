<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioMovimentacoesUsuario extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Relatório de Movimentações por Usuário';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'movimentacoes_usuario_' . date('Ymd_Hi');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $ini        = $filtros['data_inicio'] ?? date('Y-m-01');
        $fim        = $filtros['data_fim']    ?? date('Y-m-t');
        $usuario_id = $filtros['usuario_id']  ?? '';

        $this->normalizarDatas($ini, $fim);

        $params = [':ini' => $ini, ':fim' => $fim];

        // Totais
        $sqlTotais = "
            SELECT
                COUNT(*)  AS total_titulos,
                SUM(CASE WHEN tipo = 'receber' THEN valor ELSE 0 END)  AS total_receber,
                SUM(CASE WHEN tipo = 'pagar'   THEN valor ELSE 0 END)  AS total_pagar,
                SUM(CASE WHEN tipo = 'receber' THEN valor ELSE -valor END) AS saldo
            FROM (
                SELECT valor_pago AS valor, 'receber' AS tipo, NULL::integer AS usuario_id
                FROM contas_receber
                WHERE status IN ('PAGO', 'PARCIALMENTE_PAGO')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT valor_pago AS valor, 'pagar' AS tipo, NULL::integer AS usuario_id
                FROM contas_pagar
                WHERE status IN ('PAGO', 'PARCIALMENTE_PAGO', 'Pago', 'Parcialmente Pago')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL
            ) AS mov
        ";

        $stmt = $this->pdo->prepare($sqlTotais);
        $stmt->execute($params);
        $totais = $stmt->fetch(PDO::FETCH_ASSOC);

        $totais['total_titulos'] = (int)($totais['total_titulos'] ?? 0);
        $totais['total_receber'] = (float)($totais['total_receber'] ?? 0);
        $totais['total_pagar']   = (float)($totais['total_pagar'] ?? 0);
        $totais['saldo']         = (float)($totais['saldo'] ?? 0);

        // Movimentações detalhadas
        $sqlMov = "
            SELECT
                mov.data_baixa,
                COALESCE(u.nome, '—')     AS usuario_nome,
                mov.tipo,
                mov.id,
                mov.descricao,
                mov.valor,
                mov.data_vencimento,
                mov.id::text              AS identificador
            FROM (
                SELECT
                    data_pagamento          AS data_baixa,
                    NULL::integer           AS usuario_id,
                    'receber'               AS tipo,
                    id,
                    descricao,
                    valor_pago              AS valor,
                    data_vencimento
                FROM contas_receber
                WHERE status IN ('PAGO', 'PARCIALMENTE_PAGO')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT
                    data_pagamento          AS data_baixa,
                    NULL::integer           AS usuario_id,
                    'pagar'                 AS tipo,
                    id,
                    descricao,
                    valor_pago              AS valor,
                    data_vencimento
                FROM contas_pagar
                WHERE status IN ('PAGO', 'PARCIALMENTE_PAGO', 'Pago', 'Parcialmente Pago')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL
            ) AS mov
            LEFT JOIN usuarios u ON mov.usuario_id = u.id
        ";

        if (!empty($usuario_id)) {
            $sqlMov         .= ' WHERE mov.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int)$usuario_id;
        }

        $sqlMov .= ' ORDER BY mov.data_baixa DESC, mov.tipo, mov.data_vencimento';

        $stmt          = $this->pdo->prepare($sqlMov);
        $stmt->execute($params);
        $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($movimentacoes as &$m) {
            $m['valor']       = (float)($m['valor'] ?? 0);
            $m['identificador'] = $m['identificador'] ?? '#' . $m['id'];
        }

        // Info do usuário filtrado
        $usuario_info = [];
        if (!empty($usuario_id)) {
            $stmt = $this->pdo->prepare('SELECT id, nome FROM usuarios WHERE id = :id');
            $stmt->execute([':id' => (int)$usuario_id]);
            $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        return [
            'totais'        => $totais,
            'movimentacoes' => $movimentacoes,
            'usuario_info'  => $usuario_info,
            'data_inicio'   => $ini,
            'data_fim'      => $fim,
        ];
    }

    public function buscarUsuarios(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome FROM usuarios WHERE ativo = TRUE ORDER BY nome');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PDF
    // =========================================================================

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $totais        = $dados['totais'];
        $movimentacoes = $dados['movimentacoes'];
        $usuario_info  = $dados['usuario_info'];

        $html = $this->htmlResumo([
            ['label' => 'Total de Títulos',      'valor' => $totais['total_titulos']],
            ['label' => 'Contas a Receber',       'valor' => $this->brl($totais['total_receber']), 'class' => 'positivo'],
            ['label' => 'Contas a Pagar',         'valor' => $this->brl($totais['total_pagar']),   'class' => 'negativo'],
            ['label' => 'Saldo Líquido',          'valor' => $this->brl($totais['saldo']),
             'class' => $totais['saldo'] >= 0 ? 'positivo' : 'negativo'],
        ]);

        if (!empty($usuario_info)) {
            $html .= '<p style="font-size:9pt;margin-bottom:6px;">Usuário: <strong>'
                . htmlspecialchars($usuario_info['nome'] ?? '')
                . '</strong></p>';
        }

        $colunas = [
            ['label' => 'Data da Baixa',          'align' => 'left',  'width' => '110px'],
            ['label' => 'Usuário Responsável',    'align' => 'left',  'width' => '140px'],
            ['label' => 'Tipo',                   'align' => 'center','width' => '60px'],
            ['label' => 'Identificador',          'align' => 'left',  'width' => '90px'],
            ['label' => 'Valor (R$)',             'align' => 'right', 'width' => '90px'],
            ['label' => 'Vencimento Original',   'align' => 'center','width' => '100px'],
        ];

        $linhas = [];
        foreach ($movimentacoes as $m) {
            $tipoLabel = $m['tipo'] === 'receber' ? 'CR' : 'CP';
            $tipoClass = $m['tipo'] === 'receber' ? 'badge-receber positivo' : 'badge-pagar negativo';
            $linhas[] = [
                'cells' => [
                    $this->dataHora($m['data_baixa']),
                    htmlspecialchars($m['usuario_nome']),
                    '<span class="' . $tipoClass . '">' . $tipoLabel . '</span>',
                    htmlspecialchars($m['identificador']),
                    '<span class="' . ($m['tipo'] === 'receber' ? 'positivo' : 'negativo') . '">'
                        . $this->brl($m['valor'])
                        . '</span>',
                    $this->data($m['data_vencimento'] ?? ''),
                ],
            ];
        }

        $html .= $this->htmlTabela($colunas, $linhas);
        return $html;
    }
}
