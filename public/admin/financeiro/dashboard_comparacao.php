<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::manager()?->require('financeiro', true);

header('Content-Type: application/json');

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    $tipo = $_POST['tipo'] ?? '';
    $periodo1 = $_POST['periodo1'] ?? '';
    $periodo2 = $_POST['periodo2'] ?? '';
    
    if (empty($tipo) || empty($periodo1) || empty($periodo2)) {
        throw new Exception('Par?metros incompletos');
    }
    
    function extrairPeriodo($periodo, $tipo) {
        switch($tipo) {
            case 'mes_a_mes':
                $partes = explode('/', $periodo);
                return [
                    'mes' => $partes[0] ?? '01',
                    'ano' => $partes[1] ?? date('Y'),
                    'label' => $periodo
                ];
            case 'dia_a_dia':
                $data = DateTime::createFromFormat('d/m/Y', $periodo);
                return [
                    'dia' => $data->format('d'),
                    'mes' => $data->format('m'),
                    'ano' => $data->format('Y'),
                    'label' => $periodo
                ];
            case 'ano_a_ano':
                return [
                    'ano' => $periodo,
                    'label' => $periodo
                ];
            default:
                throw new Exception('Tipo de compara??o inv?lido');
        }
    }
    
    function calcularDadosPeriodo($pdo, $periodo, $tipo) {
        $sqlReceitas = "";
        $sqlDespesas = "";
        $params = [];
        
        switch($tipo) {
            case 'mes_a_mes':
                $sqlReceitas = "SELECT COALESCE(SUM(valor_recebido), 0) as total
                                FROM contas_receber 
                                WHERE status IN ('Recebido', 'Parcialmente Recebido')
                                AND EXTRACT(MONTH FROM data_recebimento) = :mes
                                AND EXTRACT(YEAR FROM data_recebimento) = :ano
                                AND data_recebimento IS NOT NULL";
                
                $sqlDespesas = "SELECT COALESCE(SUM(valor_pago), 0) as total
                                FROM contas_pagar 
                                WHERE status IN ('Pago', 'Parcialmente Pago')
                                AND EXTRACT(MONTH FROM data_pagamento) = :mes
                                AND EXTRACT(YEAR FROM data_pagamento) = :ano
                                AND data_pagamento IS NOT NULL";
                
                $params = [':mes' => $periodo['mes'], ':ano' => $periodo['ano']];
                break;
                
            case 'dia_a_dia':
                $data = $periodo['ano'] . '-' . $periodo['mes'] . '-' . $periodo['dia'];
                
                $sqlReceitas = "SELECT COALESCE(SUM(valor_recebido), 0) as total
                                FROM contas_receber 
                                WHERE status IN ('Recebido', 'Parcialmente Recebido')
                                AND DATE(data_recebimento) = :data";
                
                $sqlDespesas = "SELECT COALESCE(SUM(valor_pago), 0) as total
                                FROM contas_pagar 
                                WHERE status IN ('Pago', 'Parcialmente Pago')
                                AND DATE(data_pagamento) = :data";
                
                $params = [':data' => $data];
                break;
                
            case 'ano_a_ano':
                $sqlReceitas = "SELECT COALESCE(SUM(valor_recebido), 0) as total
                                FROM contas_receber 
                                WHERE status IN ('Recebido', 'Parcialmente Recebido')
                                AND EXTRACT(YEAR FROM data_recebimento) = :ano
                                AND data_recebimento IS NOT NULL";
                
                $sqlDespesas = "SELECT COALESCE(SUM(valor_pago), 0) as total
                                FROM contas_pagar 
                                WHERE status IN ('Pago', 'Parcialmente Pago')
                                AND EXTRACT(YEAR FROM data_pagamento) = :ano
                                AND data_pagamento IS NOT NULL";
                
                $params = [':ano' => $periodo['ano']];
                break;
        }
        
        // Movimenta??es DRE (com categoria)
        $sqlReceitasManuais = "SELECT COALESCE(SUM(valor), 0) as total
                                FROM movimentacoes 
                                WHERE tipo = 'Entrada'
                                AND categoria_dre_id IS NOT NULL";
        
        $sqlDespesasManuais = "SELECT COALESCE(SUM(valor), 0) as total
                                FROM movimentacoes 
                                WHERE tipo = 'Sa?da'
                                AND categoria_dre_id IS NOT NULL";
        
        // Adicionar filtros de per?odo para movimenta??es manuais
        switch($tipo) {
            case 'mes_a_mes':
                $sqlReceitasManuais .= " AND EXTRACT(MONTH FROM data_movimentacao) = :mes AND EXTRACT(YEAR FROM data_movimentacao) = :ano";
                $sqlDespesasManuais .= " AND EXTRACT(MONTH FROM data_movimentacao) = :mes AND EXTRACT(YEAR FROM data_movimentacao) = :ano";
                break;
            case 'dia_a_dia':
                $data = $periodo['ano'] . '-' . $periodo['mes'] . '-' . $periodo['dia'];
                $sqlReceitasManuais .= " AND DATE(data_movimentacao) = :data";
                $sqlDespesasManuais .= " AND DATE(data_movimentacao) = :data";
                break;
            case 'ano_a_ano':
                $sqlReceitasManuais .= " AND EXTRACT(YEAR FROM data_movimentacao) = :ano";
                $sqlDespesasManuais .= " AND EXTRACT(YEAR FROM data_movimentacao) = :ano";
                break;
        }
        
        // Executar queries
        $stmtReceitas = $pdo->prepare($sqlReceitas);
        $stmtReceitas->execute($params);
        $receitasTitulos = $stmtReceitas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmtDespesas = $pdo->prepare($sqlDespesas);
        $stmtDespesas->execute($params);
        $despesasTitulos = $stmtDespesas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmtReceitasManuais = $pdo->prepare($sqlReceitasManuais);
        $stmtReceitasManuais->execute($params);
        $receitasManuais = $stmtReceitasManuais->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmtDespesasManuais = $pdo->prepare($sqlDespesasManuais);
        $stmtDespesasManuais->execute($params);
        $despesasManuais = $stmtDespesasManuais->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $receitas = floatval($receitasTitulos) + floatval($receitasManuais);
        $despesas = floatval($despesasTitulos) + floatval($despesasManuais);
        $saldo = $receitas - $despesas;
        
        return [
            'receitas' => $receitas,
            'despesas' => $despesas,
            'saldo' => $saldo,
            'label' => $periodo['label']
        ];
    }
    
    // Processar per?odos
    $periodo1Data = extrairPeriodo($periodo1, $tipo);
    $periodo2Data = extrairPeriodo($periodo2, $tipo);
    
    // Calcular dados para cada per?odo
    $dadosPeriodo1 = calcularDadosPeriodo($pdo, $periodo1Data, $tipo);
    $dadosPeriodo2 = calcularDadosPeriodo($pdo, $periodo2Data, $tipo);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'periodo1' => $dadosPeriodo1,
            'periodo2' => $dadosPeriodo2
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

