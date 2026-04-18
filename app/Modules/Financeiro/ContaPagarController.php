<?php

namespace App\Modules\Financeiro;

use Exception;
use PDO;
use App\Modules\Financeiro\ContaRepository;

/**
 * ContaPagarController — camada HTTP do módulo Contas a Pagar.
 */
class ContaPagarController
{
    use ClienteHelperTrait;

    private ContaPagarService        $service;
    private FormaPagamentoRepository $fpRepo;
    private CategoriaDreRepository   $catRepo;

    private const BASE_URL = '/sistema_dm/public/admin/financeiro/contas-pagar.php';

    public function __construct(private readonly PDO $pdo)
    {
        $this->service = new ContaPagarService(new ContaPagarRepository($pdo));
        $this->fpRepo  = new FormaPagamentoRepository($pdo);
        $this->catRepo = new CategoriaDreRepository($pdo);
        $this->verificarAutenticacao();
    }

    // =========================================================================
    // Roteamento
    // =========================================================================

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

        match ($action) {
            'buscar-clientes'       => $this->buscarClientes(),
            'buscar-empresas'       => $this->buscarEmpresas(),
            'cadastrar-empresa'     => $this->cadastrarEmpresa(),
            'detalhes-cliente'      => $this->detalhesCliente(),
            'cadastrar-cliente'     => $this->cadastrarCliente(),
            'buscar-categorias-dre' => $this->buscarCategoriasDre(),
            'novo'                  => $this->novo(),
            'salvar'                => $this->salvar(),
            'editar'                => $this->editar(),
            'atualizar'             => $this->atualizar(),
            'baixar'                => $this->baixar(),
            'estornar'              => $this->estornar(),
            'excluir'               => $this->excluir(),
            'exportar-pdf'          => $this->exportarPdf(),
            default                 => $this->index(),
        };
    }

    // =========================================================================
    // Actions — views
    // =========================================================================

    private function index(): void
    {
        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
        $filtros   = $this->parseFiltros();
        $resultado = $this->service->listar($filtros, $pagina, 15);

        $contasPagar  = $resultado['dados']         ?? [];
        $total        = $resultado['total']         ?? 0;
        $totalPaginas = $resultado['total_paginas'] ?? 1;
        $clientes     = $this->clientesParaSelect();
        $contasBanco  = (new ContaRepository($this->pdo))->listar(true);
        $titulo       = 'Contas a Pagar';

        $this->view('financeiro/contas-pagar/index', compact(
            'contasPagar', 'total', 'totalPaginas', 'pagina', 'clientes', 'contasBanco', 'titulo'
        ));
    }

    private function novo(): void
    {
        $formasPagamento = $this->fpRepo->listar(true);
        $clientes        = $this->clientesParaSelect();
        $empresas        = $this->buscarEmpresasAtivas();
        $titulo          = 'Nova Conta a Pagar';

        $this->view('financeiro/contas-pagar/form', compact(
            'formasPagamento', 'clientes', 'empresas', 'titulo'
        ));
    }

    private function editar(): void
    {
        $id = !empty($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) $this->redirecionar();

        $contaPagar = (new ContaPagarRepository($this->pdo))->buscarPorId($id);
        if (!$contaPagar) {
            $this->flash('error', 'Conta a pagar não encontrada.');
            $this->redirecionar();
        }

        $formasPagamento = $this->fpRepo->listar(true);
        $clientes        = $this->clientesParaSelect();
        $titulo          = 'Editar Conta a Pagar';

        $this->view('financeiro/contas-pagar/form', compact(
            'contaPagar', 'formasPagamento', 'clientes', 'titulo'
        ));
    }

    // =========================================================================
    // Actions — writes
    // =========================================================================

    private function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirecionar();

        $dados = [
            'cliente_id'         => !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null,
            'valor'              => (float) ($_POST['valor']            ?? ($_POST['valor_original'] ?? 0)),
            'data_vencimento'    => $_POST['data_vencimento']            ?? '',
            'descricao'          => trim($_POST['descricao']            ?? ''),
            'observacoes'        => trim($_POST['observacoes']          ?? ''),
            'usuario_id'         => $_SESSION['user_id'],
        ];

        $r = $this->service->criar($dados);
        if ($r['ok']) {
            $this->flash('success', 'Conta a pagar criada com sucesso!');
            $this->redirecionar();
        }
        $this->flash('error', implode('<br>', $r['erros']));
        $this->redirecionar(self::BASE_URL . '?action=novo');
    }

    private function atualizar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirecionar();

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) { $this->flash('error', 'ID não informado.'); $this->redirecionar(); }

        $dados = [
            'cliente_id'         => !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null,
            'valor'              => (float) ($_POST['valor']            ?? ($_POST['valor_original'] ?? 0)),
            'data_vencimento'    => $_POST['data_vencimento']            ?? '',
            'descricao'          => trim($_POST['descricao']            ?? ''),
            'observacoes'        => trim($_POST['observacoes']          ?? ''),
        ];

        $r = $this->service->atualizar($id, $dados);
        $r['ok']
            ? $this->flash('success', 'Conta a pagar atualizada com sucesso!')
            : $this->flash('error', implode('<br>', $r['erros']));
        $this->redirecionar();
    }

    // =========================================================================
    // Actions — AJAX
    // =========================================================================

    private function baixar(): void
    {
        $this->requireAjaxPost();
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) $this->json(['success' => false, 'message' => 'ID não informado.']);

        $dados = [
            'valor_pago'           => !empty($_POST['valor_pago']) ? (float) $_POST['valor_pago'] : null,
            'desconto_pagamento'   => (float) ($_POST['desconto_pagamento'] ?? 0),
            'data_pagamento'       => $_POST['data_pagamento'] ?? date('Y-m-d'),
            'conta_id'             => (int) ($_POST['conta_id']         ?? 0),
            'categoria_dre_id'     => (int) ($_POST['categoria_dre_id'] ?? 0),
            'usuario_id'           => $_SESSION['user_id'],
        ];

        $r = $this->service->baixar($id, $dados);
        $this->json([
            'success' => $r['ok'],
            'message' => $r['ok'] ? 'Conta a pagar baixada com sucesso!' : implode(' ', $r['erros'] ?? []),
        ]);
    }

    private function estornar(): void
    {
        $this->requireAjaxPost();
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) $this->json(['success' => false, 'message' => 'ID não informado.']);
        
        $valorEstorno = !empty($_POST['valor_estorno']) ? (float) $_POST['valor_estorno'] : null;

        $r = $this->service->estornar($id, $valorEstorno, (int) $_SESSION['user_id']);
        $this->json([
            'success' => $r['ok'],
            'message' => $r['ok'] ? 'Estorno realizado com sucesso!' : implode(' ', $r['erros'] ?? []),
        ]);
    }

    private function excluir(): void
    {
        $this->requireAjaxPost();
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) $this->json(['success' => false, 'message' => 'ID não informado.']);

        $r = $this->service->excluir($id);
        $this->json([
            'success' => $r['ok'],
            'message' => $r['ok'] ? 'Conta a pagar excluída com sucesso!' : implode(' ', $r['erros'] ?? []),
        ]);
    }

    private function buscarCategoriasDre(): void
    {
        if (!$this->isAjax()) { http_response_code(403); exit(); }
        try {
            $tipos      = ['Despesa', 'CPV', 'Despesa Operacional', 'Despesa Financeira'];
            $categorias = $this->catRepo->listar($tipos, true);
            $this->json(['success' => true, 'data' => $categorias, 'message' => count($categorias) . ' categorias encontradas']);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => 'Erro ao buscar categorias: ' . $e->getMessage()]);
        }
    }

    private function exportarPdf(): void
    {
        require_once __DIR__ . '/../../../vendor/autoload.php';

        $vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';
        if (!file_exists($vendorAutoload)) {
            http_response_code(500);
            exit('Biblioteca Dompdf não encontrada.');
        }

        $filtros = $this->parseFiltros();
        $resultado = $this->service->listar($filtros, 1, 1000);
        $contasPagar = $resultado['dados'] ?? [];

        // Renderizar HTML
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório Contas a Pagar</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; }
                h1 { font-size: 16pt; margin: 0 0 5pt 0; }
                .header { margin-bottom: 15pt; }
                table { width: 100%; border-collapse: collapse; margin-top: 10pt; }
                th { background: #333; color: white; padding: 5pt; text-align: left; font-weight: bold; }
                td { padding: 4pt; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background: #f5f5f5; }
                .number { text-align: right; }
                .total { font-weight: bold; background: #e0e0e0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Relatório de Contas a Pagar</h1>
                <p>Gerado em: <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Fornecedor</th>
                        <th>Descrição</th>
                        <th class="number">Valor</th>
                        <th class="number">Pago</th>
                        <th class="number">Desconto</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalValor = 0;
                    $totalPago = 0;
                    $totalDesconto = 0;
                    foreach ($contasPagar as $conta):
                        $valorPago = (float)($conta['valor_pago'] ?? 0);
                        $desconto = (float)($conta['desconto'] ?? 0);
                        $totalValor += (float)$conta['valor'];
                        $totalPago += $valorPago;
                        $totalDesconto += $desconto;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($conta['cliente_nome'] ?? $conta['fornecedor'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($conta['descricao'] ?? '—'); ?></td>
                        <td class="number">R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></td>
                        <td class="number">R$ <?php echo number_format($valorPago, 2, ',', '.'); ?></td>
                        <td class="number">R$ <?php echo number_format($desconto, 2, ',', '.'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></td>
                        <td><?php echo htmlspecialchars($conta['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total">
                        <td colspan="2">TOTAL</td>
                        <td class="number">R$ <?php echo number_format($totalValor, 2, ',', '.'); ?></td>
                        <td class="number">R$ <?php echo number_format($totalPago, 2, ',', '.'); ?></td>
                        <td class="number">R$ <?php echo number_format($totalDesconto, 2, ',', '.'); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // Gerar PDF com Dompdf
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'contas_pagar_' . date('d-m-Y_H-i') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo $dompdf->output();
        exit;
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    private function parseFiltros(): array
    {
        $f = [];
        if (!empty($_GET['cliente_id'])) $f['cliente_id'] = (int) $_GET['cliente_id'];
        if (!empty($_GET['status']))     $f['status']     = $_GET['status'];
        if (!empty($_GET['data_inicio']))$f['data_inicio']= $_GET['data_inicio'];
        if (!empty($_GET['data_fim']))   $f['data_fim']   = $_GET['data_fim'];
        return $f;
    }

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) { header('Location: /sistema_dm/public/login.php'); exit(); }
        if ((int) ($_SESSION['user_role'] ?? 0) !== 1) { header('Location: /sistema_dm/public/admin/dashboard.php'); exit(); }
    }

    private function view(string $view, array $dados = []): void
    {
        extract($dados);
        require_once __DIR__ . '/../../../public/includes/header.php';
        $path = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($path)
            ? require $path
            : print "<div class='container mt-4'><div class='alert alert-danger'>View não encontrada: {$view}</div></div>";
        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    private function redirecionar(?string $url = null): never
    {
        header('Location: ' . ($url ?? self::BASE_URL)); exit();
    }

    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function json(array $dados): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados);
        exit();
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function requireAjaxPost(): void
    {
        if (!$this->isAjax() || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(403); exit(); }
    }
}
