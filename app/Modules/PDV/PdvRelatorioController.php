<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Security\PdvPermissao;
use PDO;
use RuntimeException;
use TCPDF;

final class PdvRelatorioController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvService $service,
        private readonly PdvPermissao $permissao
    ) {
    }

    public function pdf(int $caixaId): void
    {
        $this->assertAcesso($caixaId);
        $dados = $this->service->obterDadosRelatorio($caixaId);

        $empresa = $this->nomeEmpresa();
        $caixa = $dados['caixa'];
        $pdf = new TCPDF();
        $pdf->SetCreator('Sistema DM');
        $pdf->SetAuthor('Sistema DM');
        $pdf->SetTitle('Fechamento Caixa #' . (int) ($caixa['numero_caixa'] ?? 0));
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $html = '<h1 style="font-size:16px;">Relatório de Fechamento de Caixa</h1>';
        $html .= '<p><strong>Empresa:</strong> ' . htmlspecialchars($empresa) . '<br>';
        $html .= '<strong>Caixa:</strong> #' . (int) ($caixa['numero_caixa'] ?? 0) . '<br>';
        $html .= '<strong>Operador:</strong> ' . htmlspecialchars((string) ($caixa['operador_nome'] ?? '')) . '<br>';
        $html .= '<strong>Abertura:</strong> ' . $this->formatarDataHora((string) ($caixa['data_abertura'] ?? '')) . '<br>';
        $html .= '<strong>Fechamento:</strong> ' . $this->formatarDataHora((string) ($caixa['data_fechamento'] ?? '')) . '</p>';

        $html .= '<h3 style="font-size:13px;">Lançamentos</h3>';
        $html .= '<table border="1" cellpadding="4">
            <thead>
                <tr style="background-color:#efefef;">
                    <th width="70">Tipo</th>
                    <th width="70">Número</th>
                    <th width="170">Cliente</th>
                    <th width="95">Pagamento</th>
                    <th width="85">Valor</th>
                    <th width="90">Hora</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($dados['lancamentos'] as $lancamento) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($lancamento['tipo_label'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($lancamento['numero_referencia'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($lancamento['cliente_nome'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars($this->rotuloFormaPagamento((string) ($lancamento['forma_pagamento'] ?? ''))) . '</td>';
            $html .= '<td align="right">R$ ' . number_format((float) ($lancamento['valor_total'] ?? 0), 2, ',', '.') . '</td>';
            $html .= '<td>' . $this->formatarHora((string) ($lancamento['created_at'] ?? '')) . '</td>';
            $html .= '</tr>';
        }

        if ($dados['lancamentos'] === []) {
            $html .= '<tr><td colspan="6" align="center">Nenhum lançamento no caixa.</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= $this->htmlConferencia($dados);
        $html .= '<p style="margin-top:25px;">________________________________________<br>Assinatura do operador</p>';

        $pdf->writeHTML($html, true, false, true, false, '');

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="fechamento-caixa-' . (int) ($caixa['numero_caixa'] ?? 0) . '.pdf"');
        echo $pdf->Output('', 'S');
        exit();
    }

    public function termica(int $caixaId): void
    {
        $this->assertAcesso($caixaId);
        $dados = $this->service->obterDadosRelatorio($caixaId);
        $caixa = $dados['caixa'];

        $linhas = [];
        $linhas[] = $this->centralizar($this->nomeEmpresa());
        $linhas[] = $this->centralizar('FECHAMENTO DE CAIXA');
        $linhas[] = str_repeat('-', 48);
        $linhas[] = 'Caixa: #' . (int) ($caixa['numero_caixa'] ?? 0);
        $linhas[] = 'Operador: ' . $this->limitar((string) ($caixa['operador_nome'] ?? ''), 37);
        $linhas[] = 'Abertura: ' . $this->formatarDataHora((string) ($caixa['data_abertura'] ?? ''));
        $linhas[] = 'Fechamento: ' . $this->formatarDataHora((string) ($caixa['data_fechamento'] ?? ''));
        $linhas[] = str_repeat('-', 48);
        $linhas[] = 'LANCAMENTOS';

        foreach ($dados['lancamentos'] as $lancamento) {
            $numero = (string) ($lancamento['numero_referencia'] ?? '');
            $linhas[] = $this->limitar((string) ($lancamento['tipo_label'] ?? '') . ' #' . $numero, 48);
            $linhas[] = $this->limitar((string) ($lancamento['cliente_nome'] ?? ''), 48);
            $linhas[] = $this->linhaDireita(
                $this->rotuloFormaPagamento((string) ($lancamento['forma_pagamento'] ?? '')),
                'R$ ' . number_format((float) ($lancamento['valor_total'] ?? 0), 2, ',', '.')
            );
        }

        if ($dados['lancamentos'] === []) {
            $linhas[] = 'Sem lançamentos.';
        }

        $linhas[] = str_repeat('-', 48);
        $linhas[] = 'CONFERENCIA';
        foreach (['dinheiro', 'cartao', 'pix', 'a_faturar'] as $forma) {
            $linhas[] = $this->limitar(strtoupper($this->rotuloFormaPagamento($forma)), 48);
            $linhas[] = 'SISTEMA:  R$ ' . number_format((float) ($dados['sistema'][$forma] ?? 0), 2, ',', '.');
            $linhas[] = 'OPERADOR: R$ ' . number_format((float) ($dados['operador'][$forma] ?? 0), 2, ',', '.');
            $linhas[] = 'DIFEREN.: R$ ' . number_format((float) ($dados['diferencas'][$forma] ?? 0), 2, ',', '.');
        }
        $linhas[] = str_repeat('-', 48);
        $linhas[] = 'TOTAL SISTEMA:  R$ ' . number_format((float) ($dados['total_sistema'] ?? 0), 2, ',', '.');
        $linhas[] = 'TOTAL OPERADOR: R$ ' . number_format((float) ($dados['total_operador'] ?? 0), 2, ',', '.');
        $linhas[] = 'DIFERENCA TOT: R$ ' . number_format((float) ($dados['diferenca_total'] ?? 0), 2, ',', '.');
        $linhas[] = str_repeat('-', 48);
        $linhas[] = '';
        $linhas[] = 'Assinatura operador:';
        $linhas[] = '';
        $linhas[] = '______________________________';
        $linhas[] = '';

        $saida = implode("\n", $linhas) . "\n" . "\x1D\x56\x00";

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="fechamento-caixa-' . (int) ($caixa['numero_caixa'] ?? 0) . '.escpos"');
        echo $saida;
        exit();
    }

    private function htmlConferencia(array $dados): string
    {
        $linhas = [
            ['Dinheiro', 'dinheiro'],
            ['Cartão', 'cartao'],
            ['PIX', 'pix'],
            ['A Faturar', 'a_faturar'],
        ];

        $html = '<h3 style="font-size:13px; margin-top:18px;">Conferência</h3>';
        $html .= '<table border="1" cellpadding="4">
            <thead>
                <tr style="background-color:#efefef;">
                    <th width="140">Forma</th>
                    <th width="110">Sistema</th>
                    <th width="110">Operador</th>
                    <th width="110">Diferença</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($linhas as [$label, $key]) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($label) . '</td>';
            $html .= '<td align="right">R$ ' . number_format((float) ($dados['sistema'][$key] ?? 0), 2, ',', '.') . '</td>';
            $html .= '<td align="right">R$ ' . number_format((float) ($dados['operador'][$key] ?? 0), 2, ',', '.') . '</td>';
            $html .= '<td align="right">R$ ' . number_format((float) ($dados['diferencas'][$key] ?? 0), 2, ',', '.') . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr style="font-weight:bold;">';
        $html .= '<td>TOTAL</td>';
        $html .= '<td align="right">R$ ' . number_format((float) ($dados['total_sistema'] ?? 0), 2, ',', '.') . '</td>';
        $html .= '<td align="right">R$ ' . number_format((float) ($dados['total_operador'] ?? 0), 2, ',', '.') . '</td>';
        $html .= '<td align="right">R$ ' . number_format((float) ($dados['diferenca_total'] ?? 0), 2, ',', '.') . '</td>';
        $html .= '</tr>';
        $html .= '</tbody></table>';

        return $html;
    }

    private function assertAcesso(int $caixaId): void
    {
        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
        if ($usuarioId <= 0) {
            header('Location: ' . tenantUrl('login.php'));
            exit();
        }

        if (!$this->permissao->podeAcessarRelatorio($usuarioId, $caixaId)) {
            throw new RuntimeException('Sem permissão para acessar este relatório.');
        }
    }

    private function nomeEmpresa(): string
    {
        $stmt = $this->pdo->query("SELECT valor FROM configuracoes WHERE chave = 'nome_sistema' LIMIT 1");
        $nome = $stmt->fetchColumn();
        return is_string($nome) && $nome !== '' ? $nome : 'Sistema DM';
    }

    private function rotuloFormaPagamento(string $forma): string
    {
        return match ($forma) {
            'dinheiro' => 'Dinheiro',
            'cartao' => 'Cartão',
            'pix' => 'PIX',
            'a_faturar' => 'A Faturar',
            default => $forma,
        };
    }

    private function formatarDataHora(string $valor): string
    {
        if ($valor === '') {
            return '--';
        }

        $timestamp = strtotime($valor);
        return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '--';
    }

    private function formatarHora(string $valor): string
    {
        if ($valor === '') {
            return '--';
        }

        $timestamp = strtotime($valor);
        return $timestamp !== false ? date('H:i', $timestamp) : '--';
    }

    private function centralizar(string $texto): string
    {
        $texto = $this->limitar($texto, 48);
        return str_pad($texto, (int) floor((48 + strlen($texto)) / 2), ' ', STR_PAD_LEFT);
    }

    private function linhaDireita(string $esquerda, string $direita): string
    {
        $esquerda = $this->limitar($esquerda, 30);
        return str_pad($esquerda, 30) . str_pad($direita, 18, ' ', STR_PAD_LEFT);
    }

    private function limitar(string $texto, int $limite): string
    {
        return mb_strimwidth($texto, 0, $limite, '');
    }
}
