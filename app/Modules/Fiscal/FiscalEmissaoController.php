<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use App\Support\CsrfProtection;
use App\Support\PermissionGate;
use PDO;

/**
 * Controller que atende chamadas AJAX para emitir NFC-e / NFS-e,
 * listar vendas pendentes de fiscal e buscar o estado fiscal por venda.
 */
final class FiscalEmissaoController
{
    public function __construct(private readonly PDO $pdo)
    {
        PermissionGate::init($pdo);
        PermissionGate::require('fiscal');
    }

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'pendentes';
        match ($action) {
            'emitir-nfce' => $this->emitirNfce(),
            'emitir-nfse' => $this->emitirNfse(),
            'estado-venda' => $this->estadoVenda(),
            'pendentes' => $this->pendentes(),
            default => $this->pendentes(),
        };
    }

    private function emitirNfce(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            CsrfProtection::validateRequestOrFail();
            $vendaId = (int)($_POST['venda_id'] ?? 0);
            if ($vendaId <= 0) throw new \RuntimeException('venda_id obrigatório.');
            $emissor = new NfceEmissor($this->pdo);
            echo json_encode($emissor->emitir($vendaId));
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }

    private function emitirNfse(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            CsrfProtection::validateRequestOrFail();
            $vendaId = (int)($_POST['venda_id'] ?? 0);
            if ($vendaId <= 0) throw new \RuntimeException('venda_id obrigatório.');
            $emissor = new NfseNacionalEmissor($this->pdo);
            echo json_encode($emissor->emitir($vendaId));
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Retorna JSON com situação fiscal de uma venda:
     *  { tem_produto, tem_servico, status_nfce, status_nfse, total_produtos, total_servicos }
     */
    private function estadoVenda(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $vendaId = (int)($_GET['venda_id'] ?? 0);
            if ($vendaId <= 0) throw new \RuntimeException('venda_id obrigatório.');

            $stmt = $this->pdo->prepare(
                "SELECT
                    SUM(CASE WHEN UPPER(tipo_item)='PRODUTO' THEN valor_total_item ELSE 0 END) AS total_produtos,
                    SUM(CASE WHEN UPPER(tipo_item)='SERVICO' THEN valor_total_item ELSE 0 END) AS total_servicos,
                    COUNT(*) FILTER (WHERE UPPER(tipo_item)='PRODUTO') AS qtd_produtos,
                    COUNT(*) FILTER (WHERE UPPER(tipo_item)='SERVICO') AS qtd_servicos
                   FROM pdv_venda_itens WHERE venda_id = :v"
            );
            $stmt->execute([':v' => $vendaId]);
            $totais = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $nfce = (new NfceEmissor($this->pdo))->listarNfceVenda($vendaId);
            $nfse = (new NfseNacionalEmissor($this->pdo))->listarNfseVenda($vendaId);

            echo json_encode([
                'ok' => true,
                'venda_id' => $vendaId,
                'tem_produto' => (int)($totais['qtd_produtos'] ?? 0) > 0,
                'tem_servico' => (int)($totais['qtd_servicos'] ?? 0) > 0,
                'total_produtos' => (float)($totais['total_produtos'] ?? 0),
                'total_servicos' => (float)($totais['total_servicos'] ?? 0),
                'nfce' => $nfce ? [
                    'id' => (int)$nfce['id'],
                    'status' => $nfce['status'],
                    'numero' => $nfce['numero'],
                    'chave' => $nfce['chave_acesso'],
                    'motivo' => $nfce['motivo_rejeicao'],
                ] : null,
                'nfse' => $nfse ? [
                    'id' => (int)$nfse['id'],
                    'status' => $nfse['status'],
                    'numero_nfse' => $nfse['numero_nfse'],
                    'numero_rps' => $nfse['numero_rps'],
                    'motivo' => $nfse['motivo_rejeicao'],
                    'link' => $nfse['link_nfse'],
                ] : null,
            ]);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }

    private function pendentes(): void
    {
        $filtros = [
            'inicio' => $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days')),
            'fim' => $_GET['fim'] ?? date('Y-m-d'),
            'tipo' => $_GET['tipo'] ?? 'todos',
            'cliente' => trim((string)($_GET['cliente'] ?? '')),
        ];

        $params = [
            ':ini' => $filtros['inicio'],
            ':fim' => $filtros['fim'],
        ];

        $whereCliente = '';
        if ($filtros['cliente'] !== '') {
            $whereCliente = ' AND (LOWER(c.nome) LIKE :busca OR c.cpf_cnpj LIKE :busca2) ';
            $params[':busca'] = '%' . strtolower($filtros['cliente']) . '%';
            $params[':busca2'] = '%' . preg_replace('/\D/', '', $filtros['cliente']) . '%';
        }

        $sql = "
            WITH resumo AS (
                SELECT
                    v.id AS venda_id, v.numero, v.valor_total, v.created_at,
                    COALESCE(c.nome, 'Consumidor Final') AS cliente,
                    SUM(CASE WHEN UPPER(i.tipo_item)='PRODUTO' THEN i.valor_total_item ELSE 0 END) AS total_produtos,
                    SUM(CASE WHEN UPPER(i.tipo_item)='SERVICO' THEN i.valor_total_item ELSE 0 END) AS total_servicos
                  FROM pdv_vendas v
                  LEFT JOIN clientes c ON c.id = v.cliente_id
                  INNER JOIN pdv_venda_itens i ON i.venda_id = v.id
                 WHERE v.status = 'faturado'
                   AND v.created_at >= :ini::date
                   AND v.created_at < (:fim::date + INTERVAL '1 day')
                   $whereCliente
                 GROUP BY v.id, c.nome
            )
            SELECT r.*,
                   nfce.status AS status_nfce,
                   nfse.status AS status_nfse
              FROM resumo r
              LEFT JOIN LATERAL (
                  SELECT status FROM fiscal_nfce
                   WHERE venda_id = r.venda_id AND status = 'autorizada'
                   ORDER BY id DESC LIMIT 1
              ) nfce ON TRUE
              LEFT JOIN LATERAL (
                  SELECT status FROM fiscal_nfse
                   WHERE venda_id = r.venda_id AND status = 'autorizada'
                   ORDER BY id DESC LIMIT 1
              ) nfse ON TRUE
             WHERE (
                  (r.total_produtos > 0 AND nfce.status IS NULL)
                  OR
                  (r.total_servicos > 0 AND nfse.status IS NULL)
             )
             ORDER BY r.created_at DESC
             LIMIT 200
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->render('fiscal/pendentes/index', [
            'page_title' => 'Fiscal — Vendas Pendentes',
            'pendentes' => $pendentes,
            'filtros' => $filtros,
            'csrfToken' => CsrfProtection::token(),
        ]);
    }

    private function render(string $view, array $data): void
    {
        extract($data);
        require_once __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/' . $view . '.php';
        require_once __DIR__ . '/../../../public/includes/footer.php';
    }
}
