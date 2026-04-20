<?php

namespace App\Modules\Clientes;

/**
 * ClienteService - casos de uso do módulo de Clientes.
 */
class ClienteService
{
    public function __construct(
        private readonly ClienteRepository $repo
    ) {}

    // =========================================================================
    // Listar
    // =========================================================================

    public function listar(int $pagina, int $porPagina): array
    {
        $total      = $this->repo->totalRegistros();
        $totalPags  = max(1, (int) ceil($total / $porPagina));
        $pagina     = min(max(1, $pagina), $totalPags);

        return [
            'clientes'     => $this->repo->listar($pagina, $porPagina),
            'total'        => $total,
            'totalPaginas' => $totalPags,
            'pagina'       => $pagina,
        ];
    }

    // =========================================================================
    // Criar
    // =========================================================================

    /**
     * @return array{ok: bool, id?: int, erros?: string[]}
     */
    public function criar(array $dados): array
    {
        $dados = $this->normalizar($dados);
        $erros = $this->validar($dados);

        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        try {
            $id = $this->repo->criar($dados);
            return ['ok' => true, 'id' => $id];
        } catch (\Throwable $e) {
            error_log('ClienteService::criar - ' . $e->getMessage());
            return ['ok' => false, 'erros' => $this->mapearErroPersistencia($e, 'salvar')];
        }
    }

    // =========================================================================
    // Atualizar
    // =========================================================================

    /** @return array{ok: bool, erros?: string[]} */
    public function atualizar(int $id, array $dados): array
    {
        $dados = $this->normalizar($dados);
        $erros = $this->validarEdicao($id, $dados);
        if (!empty($erros)) return ['ok' => false, 'erros' => $erros];

        try {
            $this->repo->atualizar($id, $dados);
            return ['ok' => true];
        } catch (\Throwable $e) {
            error_log('ClienteService::atualizar - ' . $e->getMessage());
            return ['ok' => false, 'erros' => $this->mapearErroPersistencia($e, 'atualizar')];
        }
    }

    // =========================================================================
    // Excluir
    // =========================================================================

    /** @return array{ok: bool, erros?: string[]} */
    public function excluir(int $id): array
    {
        try {
            $this->repo->excluir($id);
            return ['ok' => true];
        } catch (\Throwable $e) {
            error_log('ClienteService::excluir - ' . $e->getMessage());
            return ['ok' => false, 'erros' => ['Erro ao excluir cliente.']];
        }
    }

    // =========================================================================
    // Validações privadas
    // =========================================================================

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function normalizar(array $dados): array
    {
        $indIeDest = trim((string)($dados['ind_ie_dest'] ?? '9'));
        if (!in_array($indIeDest, ['1', '2', '9'], true)) {
            $indIeDest = '9';
        }

        $cep = preg_replace('/\D/', '', (string)($dados['cep'] ?? '')) ?? '';
        $codigoMunicipio = preg_replace('/\D/', '', (string)($dados['codigo_municipio'] ?? '')) ?? '';

        return [
            'nome' => trim((string)($dados['nome'] ?? '')),
            'cpf_cnpj' => preg_replace('/\D/', '', (string)($dados['cpf_cnpj'] ?? '')) ?? '',
            'email' => strtolower(trim((string)($dados['email'] ?? ''))),
            'telefone' => trim((string)($dados['telefone'] ?? '')) ?: null,
            'eh_cliente' => !empty($dados['eh_cliente']),
            'eh_fornecedor' => !empty($dados['eh_fornecedor']),
            'logradouro' => trim((string)($dados['logradouro'] ?? '')) ?: null,
            'numero_endereco' => trim((string)($dados['numero_endereco'] ?? '')) ?: null,
            'complemento' => trim((string)($dados['complemento'] ?? '')) ?: null,
            'bairro' => trim((string)($dados['bairro'] ?? '')) ?: null,
            'cidade' => trim((string)($dados['cidade'] ?? '')) ?: null,
            'estado' => strtoupper(substr(trim((string)($dados['estado'] ?? '')), 0, 2)) ?: null,
            'cep' => $cep !== '' ? $cep : null,
            'codigo_municipio' => $codigoMunicipio !== '' ? $codigoMunicipio : null,
            'ie' => trim((string)($dados['ie'] ?? '')) ?: null,
            'ind_ie_dest' => $indIeDest,
            'prazo_faturamento_dias' => max(0, min(365, (int)($dados['prazo_faturamento_dias'] ?? 0))),
        ];
    }

    /** @return string[] */
    private function validar(array $d): array
    {
        $erros = [];

        $nome      = trim($d['nome'] ?? '');
        $cpfCnpj   = $d['cpf_cnpj'] ?? '';
        $email     = trim($d['email'] ?? '');
        $telefone  = trim($d['telefone'] ?? '');
        $cidade    = trim((string)($d['cidade'] ?? ''));
        $estado    = trim((string)($d['estado'] ?? ''));
        $cep       = trim((string)($d['cep'] ?? ''));
        $codigoMunicipio = trim((string)($d['codigo_municipio'] ?? ''));
        $ie        = trim((string)($d['ie'] ?? ''));
        $indIeDest = trim((string)($d['ind_ie_dest'] ?? '9'));
        $ehCliente = !empty($d['eh_cliente']);
        $ehFornecedor = !empty($d['eh_fornecedor']);
        $ehCliente = !empty($d['eh_cliente']);
        $ehFornecedor = !empty($d['eh_fornecedor']);

        if ($nome === '') {
            $erros[] = 'O nome é obrigatório.';
        }

        if ($cpfCnpj === '') {
            $erros[] = 'O CPF/CNPJ é obrigatório.';
        } elseif (!$this->validarCpfCnpj($cpfCnpj)) {
            $erros[] = 'O CPF/CNPJ informado é inválido.';
        } elseif ($this->repo->cpfCnpjExiste($cpfCnpj)) {
            $erros[] = 'Já existe um cliente cadastrado com este CPF/CNPJ.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O e-mail informado é inválido.';
        } elseif ($this->repo->emailExiste($email)) {
            $erros[] = 'Já existe um cliente com este e-mail.';
        }

        if ($telefone !== '' && !$this->validarTelefone($telefone)) {
            $erros[] = 'O telefone informado é inválido.';
        }

        if ($cidade === '') {
            $erros[] = 'A cidade é obrigatória.';
        }

        if (!$ehCliente && !$ehFornecedor) {
            $erros[] = 'Selecione ao menos um perfil: cliente e/ou fornecedor.';
        }

        if (!$ehCliente && !$ehFornecedor) {
            $erros[] = 'Selecione ao menos um perfil: cliente e/ou fornecedor.';
        }

        if ($estado === '' || !preg_match('/^[A-Z]{2}$/', $estado)) {
            $erros[] = 'A UF deve conter 2 letras.';
        }

        if ($cep !== '' && strlen($cep) !== 8) {
            $erros[] = 'O CEP deve conter 8 dígitos.';
        }

        if ($codigoMunicipio !== '' && strlen($codigoMunicipio) !== 7) {
            $erros[] = 'O codigo do municipio deve conter 7 dígitos.';
        }

        if (!in_array($indIeDest, ['1', '2', '9'], true)) {
            $erros[] = 'O indicador de IE do destinatário é inválido.';
        }

        if ($indIeDest === '1' && $ie === '') {
            $erros[] = 'A inscrição estadual é obrigatória para contribuintes de ICMS.';
        }

        return $erros;
    }

    /** @return string[] */
    private function validarEdicao(int $id, array $d): array
    {
        $erros = [];

        $nome      = trim($d['nome'] ?? '');
        $cpfCnpj   = $d['cpf_cnpj'] ?? '';
        $email     = trim($d['email'] ?? '');
        $telefone  = trim($d['telefone'] ?? '');
        $cidade    = trim((string)($d['cidade'] ?? ''));
        $estado    = trim((string)($d['estado'] ?? ''));
        $cep       = trim((string)($d['cep'] ?? ''));
        $codigoMunicipio = trim((string)($d['codigo_municipio'] ?? ''));
        $ie        = trim((string)($d['ie'] ?? ''));
        $indIeDest = trim((string)($d['ind_ie_dest'] ?? '9'));

        if ($nome === '') {
            $erros[] = 'O nome é obrigatório.';
        }

        if ($cpfCnpj === '') {
            $erros[] = 'O CPF/CNPJ é obrigatório.';
        } elseif (!$this->validarCpfCnpj($cpfCnpj)) {
            $erros[] = 'O CPF/CNPJ informado é inválido.';
        } elseif ($this->repo->cpfCnpjExiste($cpfCnpj, $id)) {
            $erros[] = 'Já existe outro cliente com este CPF/CNPJ.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O e-mail informado é inválido.';
        } elseif ($this->repo->emailExiste($email, $id)) {
            $erros[] = 'Já existe outro cliente com este e-mail.';
        }

        if ($telefone !== '' && !$this->validarTelefone($telefone)) {
            $erros[] = 'O telefone informado é inválido.';
        }

        if ($cidade === '') {
            $erros[] = 'A cidade é obrigatória.';
        }

        if ($estado === '' || !preg_match('/^[A-Z]{2}$/', $estado)) {
            $erros[] = 'A UF deve conter 2 letras.';
        }

        if ($cep !== '' && strlen($cep) !== 8) {
            $erros[] = 'O CEP deve conter 8 dígitos.';
        }

        if ($codigoMunicipio !== '' && strlen($codigoMunicipio) !== 7) {
            $erros[] = 'O codigo do municipio deve conter 7 dígitos.';
        }

        if (!in_array($indIeDest, ['1', '2', '9'], true)) {
            $erros[] = 'O indicador de IE do destinatário é inválido.';
        }

        if ($indIeDest === '1' && $ie === '') {
            $erros[] = 'A inscrição estadual é obrigatória para contribuintes de ICMS.';
        }

        return $erros;
    }

    // =========================================================================
    // Helpers de validação
    // =========================================================================

    private function validarCpfCnpj(string $valor): bool
    {
        $valor = preg_replace('/\D/', '', $valor);

        if (strlen($valor) === 11) {
            return !preg_match('/^(\d)\1{10}$/', $valor);
        }

        if (strlen($valor) === 14) {
            return !preg_match('/^(\d)\1{13}$/', $valor);
        }

        return false;
    }

    private function validarCPF(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^' . $cpf[0] . '{11}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            $m = $t + 1;

            for ($i = 0; $i < $t; $i++) {
                $d += $cpf[$i] * ($m - $i);
            }

            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$t] != $d) {
                return false;
            }
        }

        return true;
    }

    private function validarCNPJ(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^' . $cnpj[0] . '{14}$/', $cnpj)) {
            return false;
        }

        $t = strlen($cnpj) - 2;
        $d = 0;
        $m = 9;

        for ($i = 0; $i < $t; $i++) {
            $d += $cnpj[$i] * $m;
            $m--;
            if ($m < 2) {
                $m = 9;
            }
        }

        $d = ((10 * $d) % 11) % 10;
        if ($cnpj[$t] != $d) {
            return false;
        }

        $t++;
        $d = 0;
        $m = 9;

        for ($i = 0; $i < $t; $i++) {
            $d += $cnpj[$i] * $m;
            $m--;
            if ($m < 2) {
                $m = 9;
            }
        }

        $d = ((10 * $d) % 11) % 10;
        if ($cnpj[$t] != $d) {
            return false;
        }

        return true;
    }

    private function validarTelefone(string $telefone): bool
    {
        $tel = preg_replace('/\D/', '', $telefone);
        return strlen($tel) >= 10 && strlen($tel) <= 11;
    }

    /** @return string[] */
    private function mapearErroPersistencia(\Throwable $e, string $acao): array
    {
        $msg  = strtolower($e->getMessage());
        $code = (string) $e->getCode();

        if ($code === '23505' || str_contains($msg, 'duplicate key')) {
            if (str_contains($msg, 'cpf_cnpj')) {
                return ['Já existe cliente com este CPF/CNPJ.'];
            }
            if (str_contains($msg, 'email')) {
                return ['Já existe cliente com este e-mail.'];
            }
            return ['Já existe um cliente com os dados informados.'];
        }

        if ($code === '23502' || str_contains($msg, 'null value')) {
            return ['Existem campos obrigatórios não preenchidos para salvar o cliente.'];
        }

        if (
            $code === '42703'
            || str_contains($msg, 'undefined column')
            || str_contains($msg, 'column "cpf_cnpj" does not exist')
            || str_contains($msg, "coluna \"cpf_cnpj\" n?o existe")
        ) {
            return ['Estrutura do banco desatualizada: execute o script SQL mais recente (coluna cpf_cnpj).'];
        }

        return ["Erro interno ao {$acao} cliente."];
    }
}
