<?php

namespace App\Modules\Financeiro;

use App\Modules\Clientes\Cliente;
use App\Modules\Clientes\ClienteRepository;
use App\Modules\Clientes\ClienteService;
use Exception;
use PDO;

/**
 * ClienteHelperTrait — métodos compartilhados por ContaReceberController e ContaPagarController
 * para busca e detalhamento de clientes via AJAX.
 */
trait ClienteHelperTrait
{
    private function clientesParaSelect(): array
    {
        $repo = new ClienteRepository($this->pdo);
        return $repo->listar(1, 1000);
    }

    private function buscarClientes(): void
    {
        if (!$this->isAjax()) { http_response_code(403); exit(); }

        $termo  = $_GET['term'] ?? '';
        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $limite = 15;
        
        $repo = new ClienteRepository($this->pdo);
        $lista = $repo->buscarPorTermo($termo, $limite, $pagina);
        $results = [];

        foreach ($lista as $c) {
            $results[] = [
                'id'        => $c['id'],
                'text'      => $c['nome'] ?? 'Cliente #' . $c['id'],
                'nome'      => $c['nome'],
                'cpf_cnpj'  => $c['cpf_cnpj'],
                'email'     => $c['email'],
                'telefone'  => $c['telefone'],
            ];
        }

        $this->json(['success' => true, 'data' => $results, 'pagination' => ['more' => count($lista) === $limite]]);
    }

    private function detalhesCliente(): void
    {
        if (!$this->isAjax()) { http_response_code(403); exit(); }
        
        $clienteId = !empty($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;
        if (!$clienteId) {
            $this->json(['success' => false, 'message' => 'ID do cliente não informado']);
        }

        $repo = new ClienteRepository($this->pdo);
        $cliente = $repo->buscarPorId($clienteId);
        
        if (!$cliente) {
            $this->json(['success' => false, 'message' => 'Cliente não encontrado']);
        }

        $this->json([
            'success' => true,
            'data' => [
                'id'        => $cliente->id,
                'nome'      => $cliente->nome,
                'cpf_cnpj'  => $cliente->cpfCnpj,
                'email'     => $cliente->email,
                'telefone'  => $cliente->telefone,
                'cidade'    => $cliente->cidade,
                'estado'    => $cliente->estado,
            ]
        ]);
    }

    private function buscarEmpresas(): void
    {
        // Método legado removido - module "empresas" foi deletado
        $this->json(['success' => true, 'data' => [], 'pagination' => ['more' => false]]);
    }

    private function cadastrarEmpresa(): void
    {
        // Método legado removido - module "empresas" foi deletado
        $this->json(['success' => false, 'message' => 'Módulo de empresas foi removido do sistema']);
    }

    private function buscarEmpresasAtivas(): array
    {
        // Método legado removido - module "empresas" foi deletado
        return [];
    }

    private function formatarEmpresa(array $e): array
    {
        // Método legado removido - module "empresas" foi deletado
        return [];
    }

    private function cadastrarCliente(): void
    {
        if (!$this->isAjax() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(403);
            exit();
        }

        $cpfCnpjRaw = preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? '');

        $clienteData = [
            'nome'      => trim($_POST['nome']      ?? ''),
            'cpf_cnpj'  => $cpfCnpjRaw,
            'email'     => strtolower(trim($_POST['email']     ?? '')),
            'telefone'  => substr(trim($_POST['telefone']  ?? ''), 0, 20) ?: null,
            'cidade'    => trim($_POST['cidade']    ?? '') ?: null,
            'estado'    => strtoupper(substr(trim($_POST['estado'] ?? ''), 0, 2)) ?: null,
        ];

        $erros = [];
        if (!$clienteData['nome']) {
            $erros[] = 'Informe o nome do cliente.';
        }
        if (!filter_var($clienteData['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um e-mail válido.';
        }
        
        if (!$cpfCnpjRaw) {
            $erros[] = 'Informe um CPF/CNPJ válido.';
        } elseif (strlen($cpfCnpjRaw) < 11) {
            $erros[] = 'CPF/CNPJ incompleto (deve ter no mínimo 11 dígitos).';
        } elseif (strlen($cpfCnpjRaw) === 11 && !$this->validarCPF($cpfCnpjRaw)) {
            $erros[] = 'O CPF informado é inválido.';
        } elseif (strlen($cpfCnpjRaw) === 14 && !$this->validarCNPJ($cpfCnpjRaw)) {
            $erros[] = 'O CNPJ informado é inválido.';
        } elseif (strlen($cpfCnpjRaw) !== 11 && strlen($cpfCnpjRaw) !== 14) {
            $erros[] = 'CPF/CNPJ deve ter 11 ou 14 dígitos.';
        }

        if ($erros) {
            $this->json(['success' => false, 'message' => implode(' | ', $erros)]);
        }

        try {
            $service = new ClienteService(new ClienteRepository($this->pdo));
            $resultado = $service->criar($clienteData);

            if (!$resultado['ok']) {
                $this->json(['success' => false, 'message' => implode(' | ', $resultado['erros'] ?? ['Erro desconhecido'])]);
            }

            $clienteId = $resultado['id'] ?? 0;
            $repo = new ClienteRepository($this->pdo);
            $cliente = $repo->buscarPorId($clienteId);

            $this->json([
                'success' => true,
                'message' => 'Cliente cadastrado com sucesso.',
                'cliente' => [
                    'id'       => $cliente->id,
                    'nome'     => $cliente->nome,
                    'cpf_cnpj' => $cliente->cpfCnpj,
                    'email'    => $cliente->email,
                    'telefone' => $cliente->telefone,
                ]
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => 'Erro ao cadastrar cliente: ' . $e->getMessage()]);
        }
    }

    private function validarCpfCnpj(string $valor): bool
    {
        $valor = preg_replace('/\D/', '', $valor);

        if (strlen($valor) === 11) {
            return $this->validarCPF($valor);
        } elseif (strlen($valor) === 14) {
            return $this->validarCNPJ($valor);
        }

        return false;
    }

    private function validarCPF(string $cpf): bool
    {
        return strlen($cpf) === 11 && !preg_match('/^(\d)\1{10}$/', $cpf);
    }

    private function validarCNPJ(string $cnpj): bool
    {
        return strlen($cnpj) === 14 && !preg_match('/^(\d)\1{13}$/', $cnpj);
    }
}

