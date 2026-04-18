<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use App\Support\AuditLogger;
use PDO;

class RelatorioAuditoria extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Relatório de Atividade de Usuários';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'auditoria_usuarios_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $audit = new AuditLogger($this->pdo);

        $f = [
            'usuario_id'  => isset($filtros['usuario_id']) && $filtros['usuario_id'] !== '' ? (int)$filtros['usuario_id'] : null,
            'modulo'      => trim((string)($filtros['modulo']     ?? '')),
            'acao'        => trim((string)($filtros['acao']       ?? '')),
            'data_inicio' => trim((string)($filtros['data_inicio'] ?? date('Y-m-01'))),
            'data_fim'    => trim((string)($filtros['data_fim']    ?? date('Y-m-d'))),
            'busca'       => trim((string)($filtros['busca']      ?? '')),
        ];

        $usuarios = $audit->listarUsuarios();
        $logs     = $audit->buscar($f, 700);

        $totais = [
            'registros' => count($logs),
            'usuarios'  => count(array_unique(array_map(fn($l) => (string)($l['usuario_nome'] ?? ''), $logs))),
            'modulos'   => count(array_unique(array_map(fn($l) => (string)($l['modulo']       ?? ''), $logs))),
            'acoes'     => count(array_unique(array_map(fn($l) => (string)($l['acao']         ?? ''), $logs))),
        ];

        return [
            'logs'        => $logs,
            'usuarios'    => $usuarios,
            'totais'      => $totais,
            'data_inicio' => $f['data_inicio'],
            'data_fim'    => $f['data_fim'],
        ];
    }

    public function buscarUsuarios(): array
    {
        $audit = new AuditLogger($this->pdo);
        return $audit->listarUsuarios();
    }

    // =========================================================================
    // PDF
    // =========================================================================

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $totais = $dados['totais'];

        $html = $this->htmlResumo([
            ['label' => 'Registros',       'valor' => $totais['registros']],
            ['label' => 'Usuários',        'valor' => $totais['usuarios']],
            ['label' => 'Módulos',         'valor' => $totais['modulos']],
            ['label' => 'Tipos de Ação',   'valor' => $totais['acoes']],
        ]);

        $colunas = [
            ['label' => 'Data/Hora',   'align' => 'center', 'width' => '100px'],
            ['label' => 'Usuário',     'align' => 'left',   'width' => '90px'],
            ['label' => 'Módulo',      'align' => 'center', 'width' => '80px'],
            ['label' => 'Ação',        'align' => 'center', 'width' => '70px'],
            ['label' => 'Entidade',    'align' => 'left',   'width' => '80px'],
            ['label' => 'Descrição',   'align' => 'left'],
            ['label' => 'IP',          'align' => 'center', 'width' => '80px'],
        ];

        $linhas = [];
        foreach ($dados['logs'] as $log) {
            $entidade = htmlspecialchars((string)($log['entidade'] ?? ''));
            if (!empty($log['entidade_id'])) {
                $entidade .= ' #' . (int)$log['entidade_id'];
            }
            $linhas[] = ['cells' => [
                $this->dataHora((string)($log['created_at'] ?? '')),
                htmlspecialchars((string)($log['usuario_nome'] ?? '—')),
                htmlspecialchars((string)($log['modulo'] ?? '')),
                htmlspecialchars((string)($log['acao']   ?? '')),
                $entidade,
                htmlspecialchars((string)($log['descricao'] ?? '')),
                htmlspecialchars((string)($log['ip']        ?? '')),
            ]];
        }

        $html .= $this->htmlTabela($colunas, $linhas);
        return $html;
    }

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = [];

        $ini = $filtros['data_inicio'] ?? date('Y-m-01');
        $fim = $filtros['data_fim']    ?? date('Y-m-d');
        if (!empty($ini) && !empty($fim)) {
            $opcoes['periodo'] = $this->data($ini) . ' a ' . $this->data($fim);
        }

        return $opcoes;
    }
}
