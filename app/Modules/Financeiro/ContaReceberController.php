<?php

namespace App\Modules\Financeiro;

use Exception;
use PDO;
use App\Modules\Financeiro\ContaRepository;

/**
 * ContaReceberController — camada HTTP do módulo Contas a Receber.
 *
 * Endpoints AJAX mantêm o mesmo contrato de resposta do dispatcher original.
 */
class ContaReceberController
{
    use ClienteHelperTrait;

    private ContaReceberService      $service;
    private FormaPagamentoRepository $fpRepo;
    private CategoriaDreRepository   $catRepo;

    private const BASE_URL = '/sistema_dm/public/admin/financeiro/contas-receber.php';

    public function __construct(private readonly PDO $pdo)
    {
        $this->service   = new ContaReceberService(new ContaReceberRepository($pdo));
        $this->fpRepo    = new FormaPagamentoRepository($pdo);
        $this->catRepo   = new CategoriaDreRepository($pdo);
        $this->verificarAutenticacao();
    }

    // =========================================================================
    // Roteamento
    // =========================================================================

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

        match ($action) {
            'buscar-clientes'          => $this->buscarClientes(),
            'buscar-empresas'          => $this->buscarEmpresas(),
            'cadastrar-empresa'        => $this->cadastrarEmpresa(),
            'detalhes-cliente'         => $this->detalhesCliente(),
            'cadastrar-cliente'        => $this->cadastrarCliente(),
            'novo'                     => $this->novo(),
            'salvar'                   => $this->salvar(),
            'editar'                   => $this->editar(),
            'atualizar'                => $this->atualizar(),
            'baixar'                   => $this->baixar(),
            'estornar'                 => $this->estornar(),
            'excluir'                  => $this->excluir(),
            'detalhes-movimentacoes'   => $this->detalhesMovimentacoes(),
            'exportar-pdf'             => $this->exportarPdf(),
            default                    => $this->index(),
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

        $contasReceber      = $resultado['dados']         ?? [];
        $total              = $resultado['total']         ?? 0;
        $totalPaginas       = $resultado['total_paginas'] ?? 1;
        $clientes           = $this->clientesParaSelect();
        $contasBanco        = (new ContaRepository($this->pdo))->listar(true);
        $formasPagamento    = $this->fpRepo->listar(true);
        $categoriasDreReceita = $this->catRepo->listar('Receita', true);
        $titulo             = 'Contas a Receber';
        $pdo                = $this->pdo;  // Passar PDO para a view

        $this->view('financeiro/contas-receber/index', compact(
            'contasReceber', 'total', 'totalPaginas', 'pagina', 'clientes',
            'contasBanco', 'formasPagamento', 'categoriasDreReceita', 'titulo', 'pdo'
        ));
    }

    private function novo(): void
    {
        $formasPagamento = $this->fpRepo->listar(true);
        $clientes        = $this->clientesParaSelect();
        $empresas        = $this->buscarEmpresasAtivas();
        $titulo          = 'Nova Conta a Receber';

        $this->view('financeiro/contas-receber/form', compact(
            'formasPagamento', 'clientes', 'empresas', 'titulo'
        ));
    }

    private function editar(): void
    {
        $id = !empty($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) $this->redirecionar();

        $contaReceber = (new ContaReceberRepository($this->pdo))->buscarPorId($id);
        if (!$contaReceber) {
            $this->flash('error', 'Conta a receber não encontrada.');
            $this->redirecionar();
        }

        $formasPagamento = $this->fpRepo->listar(true);
        $clientes        = $this->clientesParaSelect();
        $titulo          = 'Editar Conta a Receber';

        $this->view('financeiro/contas-receber/form', compact(
            'contaReceber', 'formasPagamento', 'clientes', 'titulo'
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
            'forma_pagamento_id' => (int) ($_POST['forma_pagamento_id'] ?? 0),
            'valor'              => (float) ($_POST['valor_original']   ?? 0),
            'data_vencimento'    => $_POST['data_vencimento']            ?? '',
            'descricao'          => trim($_POST['descricao']            ?? ''),
            'observacoes'        => trim($_POST['observacoes']          ?? ''),
            'usuario_id'         => $_SESSION['user_id'],
        ];

        $r = $this->service->criar($dados);
        if ($r['ok']) {
            $this->flash('success', 'Conta a receber criada com sucesso!');
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
            'forma_pagamento_id' => (int) ($_POST['forma_pagamento_id'] ?? 0),
            'valor'              => (float) ($_POST['valor_original']   ?? 0),
            'data_vencimento'    => $_POST['data_vencimento']            ?? '',
            'descricao'          => trim($_POST['descricao']            ?? ''),
            'observacoes'        => trim($_POST['observacoes']          ?? ''),
        ];

        $r = $this->service->atualizar($id, $dados);
        $r['ok']
            ? $this->flash('success', 'Conta a receber atualizada com sucesso!')
            : $this->flash('error', implode('<br>', $r['erros']));
        $this->redirecionar();
    }

    // =========================================================================
    // Actions — AJAX
    // =========================================================================

    /**
     * Recebe uma conta a receber.
     * 
     * REGRA CORRIGIDA:
     * - valor_recebido = valor_pago - desconto_recebimento
     * - Se valor_recebido < valor_conta → RECEBIMENTO PARCIAL (PENDENTE)
     * - Se valor_recebido >= valor_conta → PAGO
     */
    private function baixar(): void
    {
        $this->requireAjaxPost();
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) $this->json(['success' => false, 'message' => 'ID não informado.']);

        // Validações no controller
        $valorRecebidoEffetivo = !empty($_POST['valor_recebido']) ? (float) $_POST['valor_recebido'] : null;
        if ($valorRecebidoEffetivo === null || $valorRecebidoEffetivo <= 0) {
            $this->json(['success' => false, 'message' => 'Valor recebido deve ser informado e maior que zero.']);
        }

        $desconto = (float) ($_POST['desconto_recebimento'] ?? 0);
        // CORRIGIDO: Valor creditado NESTE recebimento = valor recebido + desconto
        $valorRecebido = $valorRecebidoEffetivo + $desconto;

        if ($valorRecebido < 0) {
            $this->json(['success' => false, 'message' => 'Valor recebido não pode ser negativo.']);
        }
        
        // CORRIGIDO: Validar que desconto só funciona quando TOTAL ACUMULADO quita a conta
        $conta = (new ContaReceberRepository($this->pdo))->buscarPorId($id);
        $valorTotal = (float)$conta['valor'];
        $valorPagoAnterior = (float)($conta['valor_pago'] ?? 0);
        $totalAcumulado = $valorPagoAnterior + $valorRecebido;
        
        if ($desconto > 0 && $totalAcumulado < $valorTotal) {
            $this->json(['success' => false, 'message' => 'Desconto só é permitido quando o total acumulado quita completamente a conta.']);
        }

        $dados = [
            'forma_pagamento_id'   => (int) ($_POST['forma_pagamento_id'] ?? 0),
            'valor_recebido'       => $valorRecebidoEffetivo,
            'desconto_recebimento' => $desconto,
            'data_recebimento'     => $_POST['data_recebimento'] ?? date('Y-m-d'),
            'categoria_dre_id'     => (int) ($_POST['categoria_dre_id'] ?? 0),
            'usuario_id'           => $_SESSION['user_id'],
        ];

        $r = $this->service->baixar($id, $dados);
        $mensagem = implode(' ', $r['erros'] ?? []);
        $requerConfiguracao = str_contains($mensagem, 'nao possui adquirente e/ou prazo cadastrado')
            || str_contains($mensagem, 'nao possui banco configurado');

        $this->json([
            'success' => $r['ok'],
            'message' => $r['ok'] ? 'Conta recebida com sucesso!' : $mensagem,
            'requires_payment_config' => !$r['ok'] && $requerConfiguracao,
            'redirect_url' => !$r['ok'] && $requerConfiguracao ? FormaPagamentoFinanceiroService::CADASTRO_URL : null,
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

        try {
            $r = $this->service->excluir($id);
        } catch (NfeVinculadaException $e) {
            $this->json([
                'sucesso' => false,
                'tipo' => 'nfe_vinculada',
                'mensagem' => $e->getMessage(),
                'numero_nfe' => $e->getNumeroNfe(),
            ]);
        }
        $this->json([
            'success' => $r['ok'],
            'message' => $r['ok'] ? 'Conta a receber excluída com sucesso!' : implode(' ', $r['erros'] ?? []),
        ]);
    }

    private function detalhesMovimentacoes(): void
    {
        // Log para debug
        error_log('detalhesMovimentacoes chamado');
        error_log('isAjax: ' . ($this->isAjax() ? 'true' : 'false'));
        error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
        
        $this->requireAjaxPost();
        
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        error_log('ID recebido: ' . $id);
        
        if (!$id) {
            $this->json(['ok' => false, 'erro' => 'ID inválido', 'movimentacoes' => []]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT tipo, valor, desconto, created_at as data_movimentacao
                FROM movimentacoes
                WHERE conta_receber_id = :id
                ORDER BY created_at ASC, id ASC
            ");
            $stmt->execute([':id' => $id]);
            $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log('Movimentações encontradas: ' . count($movimentacoes));

            $this->json(['ok' => true, 'movimentacoes' => $movimentacoes]);
        } catch (\Exception $e) {
            error_log('Erro ao buscar movimentacoes: ' . $e->getMessage());
            $this->json(['ok' => false, 'erro' => $e->getMessage(), 'movimentacoes' => []]);
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
        $contasReceber = $resultado['dados'] ?? [];

        // Renderizar HTML
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório Contas a Receber</title>
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
                <h1>Relatório de Contas a Receber</h1>
                <p>Gerado em: <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th class="number">Valor</th>
                        <th class="number">Recebido</th>
                        <th class="number">Desconto</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalValor = 0;
                    $totalRecebido = 0;
                    $totalDesconto = 0;
                    foreach ($contasReceber as $conta):
                        $valorRecebido = (float)($conta['valor_pago'] ?? 0);
                        $desconto = (float)($conta['desconto'] ?? 0);
                        $totalValor += (float)$conta['valor'];
                        $totalRecebido += $valorRecebido;
                        $totalDesconto += $desconto;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($conta['cliente_nome'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($conta['descricao'] ?? '—'); ?></td>
                        <td class="number">R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></td>
                        <td class="number">R$ <?php echo number_format($valorRecebido, 2, ',', '.'); ?></td>
                        <td class="number">R$ <?php echo number_format($desconto, 2, ',', '.'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></td>
                        <td><?php echo htmlspecialchars($conta['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total">
                        <td colspan="2">TOTAL</td>
                        <td class="number">R$ <?php echo number_format($totalValor, 2, ',', '.'); ?></td>
                        <td class="number">R$ <?php echo number_format($totalRecebido, 2, ',', '.'); ?></td>
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

        $filename = 'contas_receber_' . date('d-m-Y_H-i') . '.pdf';
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
        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'erro' => 'Não autenticado']);
                exit();
            }
            header('Location: /sistema_dm/public/login.php');
            exit();
        }
        if ((int) ($_SESSION['user_role'] ?? 0) !== 1) {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'erro' => 'Sem permissão']);
                exit();
            }
            header('Location: /sistema_dm/public/admin/dashboard.php');
            exit();
        }
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
        if (!$this->isAjax() || $_SERVER['REQUEST_METHOD'] !== 'POST') { 
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'erro' => 'Requisição inválida']);
            exit();
        }
    }
}
