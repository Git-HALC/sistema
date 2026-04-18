<?php

use App\Modules\Financeiro\ContaRepository;
use App\Modules\Financeiro\CategoriaDreRepository;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /sistema_dm/public/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';

$database = Database::getInstance();
$pdo = $database->getConnection();
\App\Support\PermissionGate::init($pdo);
\App\Support\PermissionGate::require('financeiro');
$contaRepo = new ContaRepository($pdo);
$catRepo = new CategoriaDreRepository($pdo);

$action = $_POST['action'] ?? ($_GET['action'] ?? 'listar');
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'novo':
        $contas = $contaRepo->listar();
        $categoriasReceita = $catRepo->listar('Receita', true);
        // Para saídas, incluir múltiplos tipos de despesa
        $categoriasDespesa = $catRepo->listar(['Dedução', 'CPV', 'Despesa Operacional', 'Despesa Financeira', 'Tributo'], true);
        
        // Debug: mostrar categorias carregadas
        error_log("Categorias Receita carregadas: " . count($categoriasReceita));
        error_log("Categorias Despesa carregadas: " . count($categoriasDespesa));
        foreach ($categoriasDespesa as $cat) {
            error_log("Despesa: ID={$cat['id']}, Nome={$cat['nome']}, Tipo={$cat['tipo']}");
        }
        
        $titulo = 'Nova Movimentação Financeira';
        include __DIR__ . '/../../../app/views/financeiro/movimentacoes/form.php';
        break;
        
    case 'salvar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        $tipo = $_POST['tipo'] ?? '';
        $contaId = (int)($_POST['conta_id'] ?? 0);
        $categoriaDreId = (int)($_POST['categoria_dre_id'] ?? 0);
        $valor = (float)($_POST['valor'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $dataMovimentacao = $_POST['data_movimentacao'] ?? date('Y-m-d');
        $usuarioId = $_SESSION['user_id'] ?? null;
        
        if (empty($tipo) || !in_array($tipo, ['Entrada', 'Saída'])) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Tipo de movimentação inválido.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php?action=novo');
            exit();
        }
        
        if ($contaId <= 0) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Selecione uma conta.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php?action=novo');
            exit();
        }
        
        if ($categoriaDreId <= 0) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Selecione uma categoria DRE.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php?action=novo');
            exit();
        }
        
        if ($valor <= 0) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Valor deve ser maior que zero.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php?action=novo');
            exit();
        }
        
        // Validar data da movimentação (não pode ser anterior à data atual, mas permite mesma data)
        date_default_timezone_set('America/Sao_Paulo');
        $dataAtual = date('Y-m-d');
        
        // Debug: mostrar as datas sendo comparadas
        error_log("Data recebida: " . $dataMovimentacao);
        error_log("Data atual (Brasil): " . $dataAtual);
        error_log("Timezone: " . date_default_timezone_get());
        error_log("Comparação: " . ($dataMovimentacao < $dataAtual ? 'MENOR' : 'IGUAL ou MAIOR'));
        
        // Permitir data atual ou futura, mas não anterior
        if ($dataMovimentacao < $dataAtual) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Data da movimentação não pode ser anterior à data atual. Data recebida: ' . $dataMovimentacao . ', Data atual: ' . $dataAtual];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php?action=novo');
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Converter data para timestamp com hora (00:00:00 se não informada)
            $dataMovimentacaoTimestamp = $dataMovimentacao;
            if (strlen($dataMovimentacao) === 10) {
                $dataMovimentacaoTimestamp = $dataMovimentacao . ' 00:00:00';
            }
            
            // Inserir movimentação
            $sql = "INSERT INTO movimentacoes 
                    (conta_id, tipo, valor, descricao, categoria_dre_id, data_movimentacao)
                    VALUES (:conta_id, :tipo, :valor, :descricao, :categoria_dre_id, :data_movimentacao)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':conta_id' => $contaId,
                ':tipo' => $tipo,
                ':valor' => $valor,
                ':descricao' => $descricao ?: 'Movimentação DRE',
                ':categoria_dre_id' => $categoriaDreId,
                ':data_movimentacao' => $dataMovimentacaoTimestamp
            ]);
            
            // Recalcular saldo manualmente (simples sum de todas as movimentações)
            $sqlCalc = "SELECT 
                            c.saldo_inicial + COALESCE(SUM(
                                CASE WHEN m.tipo = 'Entrada' THEN m.valor ELSE -m.valor END
                            ), 0) as novo_saldo
                        FROM contas c
                        LEFT JOIN movimentacoes m ON c.id = m.conta_id
                        WHERE c.id = :conta_id
                        GROUP BY c.id, c.saldo_inicial";
            $stmtCalc = $pdo->prepare($sqlCalc);
            $stmtCalc->execute([':conta_id' => $contaId]);
            $resultado = $stmtCalc->fetch(PDO::FETCH_ASSOC);
            
            // Atualizar saldo atual
            $sqlUpdate = "UPDATE contas SET saldo_atual = :novo_saldo WHERE id = :conta_id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':novo_saldo' => $resultado['novo_saldo'] ?? 0,
                ':conta_id' => $contaId
            ]);
            
            $pdo->commit();
            $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => 'Movimentação financeira registrada com sucesso!'];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao criar movimentação financeira: " . $e->getMessage());
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Erro ao registrar movimentação: ' . $e->getMessage()];
        }
        
        header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
        exit();
        
    case 'atualizar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $contaId = (int)($_POST['conta_id'] ?? 0);
        $valor = (float)($_POST['valor'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $dataMovimentacao = $_POST['data_movimentacao'] ?? date('Y-m-d');
        $categoriaDreId = (int)($_POST['categoria_dre_id'] ?? 0);
        $usuarioId = $_SESSION['user_id'] ?? null;
        
        if ($id <= 0) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'ID inválido.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        if (empty($tipo) || !in_array($tipo, ['Entrada', 'Saída'])) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Tipo de movimentação inválido.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        if ($contaId <= 0) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Selecione uma conta.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        if ($valor <= 0) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Valor deve ser maior que zero.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        // Validar data da movimentação (não pode ser anterior à data atual, mas permite mesma data)
        date_default_timezone_set('America/Sao_Paulo');
        $dataAtual = date('Y-m-d');
        
        // Debug: mostrar as datas sendo comparadas
        error_log("UPDATE - Data recebida: " . $dataMovimentacao);
        error_log("UPDATE - Data atual (Brasil): " . $dataAtual);
        error_log("UPDATE - Timezone: " . date_default_timezone_get());
        error_log("UPDATE - Comparação: " . ($dataMovimentacao < $dataAtual ? 'MENOR' : 'IGUAL ou MAIOR'));
        
        // Permitir data atual ou futura, mas não anterior
        if ($dataMovimentacao < $dataAtual) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Data da movimentação não pode ser anterior à data atual. Data recebida: ' . $dataMovimentacao . ', Data atual: ' . $dataAtual];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        if ($categoriaDreId <= 0) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Selecione uma categoria DRE.'];
            header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Validar se a movimentação é manual (sem referência a contas_receber ou contas_pagar)
            $stmtCheck = $pdo->prepare("SELECT conta_receber_id, conta_pagar_id FROM movimentacoes WHERE id = :id");
            $stmtCheck->execute([':id' => $id]);
            $movCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$movCheck) {
                throw new Exception('Movimentação não encontrada.');
            }
            
            // Impedir edição de movimentações que vêm de contas_receber ou contas_pagar
            if (!empty($movCheck['conta_receber_id']) || !empty($movCheck['conta_pagar_id'])) {
                throw new Exception('Não é permitido editar movimentações provenientes de contas a receber ou contas a pagar. Use a função de Estorno na tela de títulos.');
            }
            
            // Buscar movimentação original para validar
            $stmtCheck = $pdo->prepare("SELECT conta_receber_id, conta_pagar_id FROM movimentacoes WHERE id = :id");
            $stmtCheck->execute([':id' => $id]);
            $movCheckEdit = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$movCheckEdit) {
                throw new Exception('Movimentação não encontrada.');
            }
            
            // Impedir edição de movimentações que vêm de contas_receber ou contas_pagar
            if (!empty($movCheckEdit['conta_receber_id']) || !empty($movCheckEdit['conta_pagar_id'])) {
                throw new Exception('Não é permitido editar movimentações provenientes de contas a receber ou contas a pagar. Use a função de Estorno na tela de títulos.');
            }
            
            // Buscar movimentação original
            $stmt = $pdo->prepare("SELECT conta_id, tipo, valor FROM movimentacoes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $movOriginal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$movOriginal) {
                throw new Exception('Movimentação não encontrada.');
            }
            
            // Converter data para timestamp com hora
            $dataMovimentacaoTimestamp = $dataMovimentacao;
            if (strlen($dataMovimentacao) === 10) {
                $dataMovimentacaoTimestamp = $dataMovimentacao . ' 00:00:00';
            }
            
            // Atualizar movimentação
            $sql = "UPDATE movimentacoes 
                    SET conta_id = :conta_id, tipo = :tipo, valor = :valor, descricao = :descricao,
                        categoria_dre_id = :categoria_dre_id, data_movimentacao = :data_movimentacao
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':conta_id' => $contaId,
                ':tipo' => $tipo,
                ':valor' => $valor,
                ':descricao' => $descricao ?: 'Movimentação DRE',
                ':categoria_dre_id' => $categoriaDreId,
                ':data_movimentacao' => $dataMovimentacaoTimestamp
            ]);
            
            // Recalcular saldo da conta original (se mudou de conta)
            if ($movOriginal['conta_id'] != $contaId) {
                $sqlCalcOriginal = "SELECT 
                                        c.saldo_inicial + COALESCE(SUM(
                                            CASE WHEN m.tipo = 'Entrada' THEN m.valor ELSE -m.valor END
                                        ), 0) as novo_saldo
                                    FROM contas c
                                    LEFT JOIN movimentacoes m ON c.id = m.conta_id
                                    WHERE c.id = :conta_id
                                    GROUP BY c.id, c.saldo_inicial";
                $stmtCalcOriginal = $pdo->prepare($sqlCalcOriginal);
                $stmtCalcOriginal->execute([':conta_id' => $movOriginal['conta_id']]);
                $resultadoOriginal = $stmtCalcOriginal->fetch(PDO::FETCH_ASSOC);
                
                $sqlUpdateOriginal = "UPDATE contas SET saldo_atual = :novo_saldo WHERE id = :conta_id";
                $stmtUpdateOriginal = $pdo->prepare($sqlUpdateOriginal);
                $stmtUpdateOriginal->execute([
                    ':novo_saldo' => $resultadoOriginal['novo_saldo'] ?? 0,
                    ':conta_id' => $movOriginal['conta_id']
                ]);
            }
            
            // Recalcular saldo da nova conta
            $sqlCalc = "SELECT 
                            c.saldo_inicial + COALESCE(SUM(
                                CASE WHEN m.tipo = 'Entrada' THEN m.valor ELSE -m.valor END
                            ), 0) as novo_saldo
                        FROM contas c
                        LEFT JOIN movimentacoes m ON c.id = m.conta_id
                        WHERE c.id = :conta_id
                        GROUP BY c.id, c.saldo_inicial";
            $stmtCalc = $pdo->prepare($sqlCalc);
            $stmtCalc->execute([':conta_id' => $contaId]);
            $resultado = $stmtCalc->fetch(PDO::FETCH_ASSOC);
            
            // Atualizar saldo atual
            $sqlUpdate = "UPDATE contas SET saldo_atual = :novo_saldo WHERE id = :conta_id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':novo_saldo' => $resultado['novo_saldo'] ?? 0,
                ':conta_id' => $contaId
            ]);
            
            $pdo->commit();
            $_SESSION['mensagem'] = ['tipo' => 'success', 'texto' => 'Movimentação atualizada com sucesso!'];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao atualizar movimentação: " . $e->getMessage());
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Erro ao atualizar movimentação: ' . $e->getMessage()];
        }
        
        header('Location: /sistema_dm/public/admin/financeiro/movimentacoes.php');
        exit();
        
    case 'excluir':
        if (!$isAjax || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.1 403 Forbidden');
            exit();
        }
        
        $id = $_POST['id'] ?? null;
        if (!$id) {
            $response['message'] = 'ID não informado.';
        } else {
            try {
                // Validar se a movimentação é manual (sem referência a contas_receber ou contas_pagar)
                $stmtCheck = $pdo->prepare("SELECT conta_id, conta_receber_id, conta_pagar_id FROM movimentacoes WHERE id = :id");
                $stmtCheck->execute([':id' => $id]);
                $movCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if (!$movCheck) {
                    $response['message'] = 'Movimentação não encontrada.';
                } elseif (!empty($movCheck['conta_receber_id']) || !empty($movCheck['conta_pagar_id'])) {
                    // Impedir deleção de movimentações que vêm de contas_receber ou contas_pagar
                    $response['message'] = 'Não é permitido excluir movimentações provenientes de contas a receber ou contas a pagar. Use a função de Estorno na tela de títulos.';
                } else {
                    $pdo->beginTransaction();
                    
                    // Movimentações manuais DRE podem ser excluídas sem estorno de título
                    $stmtDel = $pdo->prepare("DELETE FROM movimentacoes WHERE id = :id");
                    $stmtDel->execute([':id' => $id]);
                    
                    // Recalcular saldo da conta afetada
                    $sqlCalc = "SELECT 
                                    c.saldo_inicial + COALESCE(SUM(
                                        CASE WHEN m.tipo = 'Entrada' THEN m.valor ELSE -m.valor END
                                    ), 0) as novo_saldo
                                FROM contas c
                                LEFT JOIN movimentacoes m ON c.id = m.conta_id
                                WHERE c.id = :conta_id
                                GROUP BY c.id, c.saldo_inicial";
                    $stmtCalc = $pdo->prepare($sqlCalc);
                    $stmtCalc->execute([':conta_id' => $movCheck['conta_id']]);
                    $resultado = $stmtCalc->fetch(PDO::FETCH_ASSOC);
                    
                    // Atualizar saldo atual
                    $sqlUpdate = "UPDATE contas SET saldo_atual = :novo_saldo WHERE id = :conta_id";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->execute([
                        ':novo_saldo' => $resultado['novo_saldo'] ?? 0,
                        ':conta_id' => $movCheck['conta_id']
                    ]);
                    
                    $pdo->commit();
                    $response['success'] = true;
                    $response['message'] = 'Movimentação excluída com sucesso!';
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Erro ao excluir movimentação: " . $e->getMessage());
                $response['message'] = 'Erro ao excluir movimentação: ' . $e->getMessage();
            }
        }
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response);
        exit();
        
    case 'exportar-pdf':
        require_once __DIR__ . '/../../../vendor/autoload.php';
        
        // Filtros
        $where = [];
        $params = [];
        
        if (!empty($_GET['conta_id'])) {
            $where[] = "m.conta_id = :conta_id";
            $params[':conta_id'] = (int)$_GET['conta_id'];
        }
        
        if (!empty($_GET['tipo'])) {
            $where[] = "m.tipo = :tipo";
            $params[':tipo'] = $_GET['tipo'];
        }
        
        if (!empty($_GET['data_inicio'])) {
            $where[] = "m.data_movimentacao >= :data_inicio";
            $params[':data_inicio'] = $_GET['data_inicio'];
        }
        
        if (!empty($_GET['data_fim'])) {
            $where[] = "m.data_movimentacao <= :data_fim";
            $params[':data_fim'] = $_GET['data_fim'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Buscar todas as movimentações (sem paginação)
        $sql = "SELECT m.*, c.nome as conta_nome
                FROM movimentacoes m
                LEFT JOIN contas c ON m.conta_id = c.id
                $whereClause
                ORDER BY m.data_movimentacao DESC, m.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Renderizar HTML
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório Movimentações Financeiras</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; }
                h1 { font-size: 16pt; margin: 0 0 5pt 0; }
                .header { margin-bottom: 15pt; }
                table { width: 100%; border-collapse: collapse; margin-top: 10pt; }
                th { background: #333; color: white; padding: 5pt; text-align: left; font-weight: bold; }
                td { padding: 4pt; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background: #f5f5f5; }
                .number { text-align: right; }
                .entrada { background-color: #f0f8f0; }
                .saida { background-color: #f8f0f0; }
                .total { font-weight: bold; background: #e0e0e0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Relatório de Movimentações Financeiras</h1>
                <p>Gerado em: <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Conta</th>
                        <th>Tipo</th>
                        <th class="number">Valor</th>
                        <th>Data</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalEntrada = 0;
                    $totalSaida = 0;
                    foreach ($movimentacoes as $mov):
                        $valor = (float)$mov['valor'];
                        if ($mov['tipo'] === 'Entrada') {
                            $totalEntrada += $valor;
                        } else {
                            $totalSaida += $valor;
                        }
                    ?>
                    <tr class="<?php echo $mov['tipo'] === 'Entrada' ? 'entrada' : 'saida'; ?>">
                        <td><?php echo htmlspecialchars($mov['conta_nome'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($mov['tipo']); ?></td>
                        <td class="number">R$ <?php echo number_format($valor, 2, ',', '.'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($mov['data_movimentacao'])); ?></td>
                        <td><?php echo htmlspecialchars($mov['descricao'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total">
                        <td colspan="2">TOTAIS</td>
                        <td class="number entrada" style="background-color: #cff0cf;">
                            <strong>ENTRADAS:</strong> R$ <?php echo number_format($totalEntrada, 2, ',', '.'); ?>
                        </td>
                        <td class="number saida" style="background-color: #f0cccc;" colspan="2">
                            <strong>SAÍDAS:</strong> R$ <?php echo number_format($totalSaida, 2, ',', '.'); ?>
                        </td>
                    </tr>
                    <tr class="total">
                        <td colspan="2">SALDO</td>
                        <td class="number"><strong>R$ <?php echo number_format($totalEntrada - $totalSaida, 2, ',', '.'); ?></strong></td>
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

        $filename = 'movimentacoes_' . date('d-m-Y_H-i') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo $dompdf->output();
        exit;
        
    case 'listar':
    default:
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $limite = 20;
        $offset = ($pagina - 1) * $limite;
        
        // Filtros
        $where = [];
        $params = [];
        
        if (!empty($_GET['conta_id'])) {
            $where[] = "m.conta_id = :conta_id";
            $params[':conta_id'] = (int)$_GET['conta_id'];
        }
        
        if (!empty($_GET['tipo'])) {
            $where[] = "m.tipo = :tipo";
            $params[':tipo'] = $_GET['tipo'];
        }
        
        if (!empty($_GET['data_inicio'])) {
            $where[] = "m.data_movimentacao >= :data_inicio";
            $params[':data_inicio'] = $_GET['data_inicio'];
        }
        
        if (!empty($_GET['data_fim'])) {
            $where[] = "m.data_movimentacao <= :data_fim";
            $params[':data_fim'] = $_GET['data_fim'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Contar total
        $sqlCount = "SELECT COUNT(*) as total FROM movimentacoes m $whereClause";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPaginas = ceil($total / $limite);
        
        // Buscar movimentações
        $sql = "SELECT m.*, c.nome as conta_nome
                FROM movimentacoes m
                LEFT JOIN contas c ON m.conta_id = c.id
                $whereClause
                ORDER BY m.data_movimentacao DESC, m.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $contas = $contaRepo->listar();
        $categoriasReceita = $catRepo->listar('Receita', true);
        $categoriasDespesa = $catRepo->listar(['Dedução', 'CPV', 'Despesa Operacional', 'Despesa Financeira', 'Tributo'], true);
        $titulo = 'Movimentações Financeiras';
        include __DIR__ . '/../../../app/views/financeiro/movimentacoes/index.php';
        break;
}


