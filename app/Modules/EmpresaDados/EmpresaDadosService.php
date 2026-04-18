<?php

namespace App\Modules\EmpresaDados;

/**
 * Service do cadastro local de dados da empresa.
 */
class EmpresaDadosService
{
    /**
     * @param EmpresaDadosRepository $repository Repositorio da empresa local.
     */
    public function __construct(private readonly EmpresaDadosRepository $repository)
    {
    }

    /**
     * Retorna o cadastro atual da empresa.
     *
     * @return array<string, mixed>|null
     */
    public function obter(): ?array
    {
        return $this->repository->buscarEmpresaAtual();
    }

    /**
     * Valida e persiste os dados da empresa.
     *
     * @param array<string, mixed> $dados Dados recebidos do formulario.
     * @param array<string, mixed> $arquivos Arquivos recebidos do formulario.
     * @return array{ok: bool, erros: string[], dados: array<string, mixed>}
     */
    public function salvar(array $dados, array $arquivos = []): array
    {
        $normalizado = $this->normalizar($dados);
        $erros = $this->validar($normalizado);
        $erros = array_merge($erros, $this->validarArquivoCertificado($arquivos['certificado_arquivo'] ?? null));

        if ($erros !== []) {
            return [
                'ok' => false,
                'erros' => $erros,
                'dados' => $normalizado,
            ];
        }

        $upload = $this->armazenarCertificado($arquivos['certificado_arquivo'] ?? null);
        if ($upload['erro'] !== null) {
            return [
                'ok' => false,
                'erros' => [$upload['erro']],
                'dados' => $normalizado,
            ];
        }

        if ($upload['path'] !== null) {
            $normalizado['certificado_path'] = $upload['path'];
        }

        $ok = $this->repository->salvarEmpresa($normalizado);

        return [
            'ok' => $ok,
            'erros' => $ok ? [] : ['Nao foi possivel salvar os dados da empresa.'],
            'dados' => $normalizado,
        ];
    }

    /**
     * Normaliza os dados vindos do formulario.
     *
     * @param array<string, mixed> $dados Dados brutos.
     * @return array<string, mixed>
     */
    private function normalizar(array $dados): array
    {
        return [
            'nome' => trim((string)($dados['nome'] ?? '')),
            'cnpj' => preg_replace('/\D/', '', (string)($dados['cnpj'] ?? '')) ?? '',
            'contato' => trim((string)($dados['contato'] ?? '')),
            'email' => mb_strtolower(trim((string)($dados['email'] ?? ''))),
            'telefone' => preg_replace('/\D/', '', (string)($dados['telefone'] ?? '')) ?? '',
            'logradouro' => trim((string)($dados['logradouro'] ?? '')),
            'numero' => trim((string)($dados['numero'] ?? '')),
            'complemento' => trim((string)($dados['complemento'] ?? '')),
            'bairro' => trim((string)($dados['bairro'] ?? '')),
            'cidade' => trim((string)($dados['cidade'] ?? '')),
            'uf' => strtoupper(substr(trim((string)($dados['uf'] ?? '')), 0, 2)),
            'cep' => preg_replace('/\D/', '', (string)($dados['cep'] ?? '')) ?? '',
            'inscricao_estadual' => trim((string)($dados['inscricao_estadual'] ?? '')),
            'inscricao_municipal' => trim((string)($dados['inscricao_municipal'] ?? '')),
            'codigo_municipio' => preg_replace('/\D/', '', (string)($dados['codigo_municipio'] ?? '')) ?? '',
            'regime_tributario' => trim((string)($dados['regime_tributario'] ?? '1')),
            'ambiente_nfe' => trim((string)($dados['ambiente_nfe'] ?? '2')),
            'serie_nfe' => substr(str_pad(preg_replace('/\D/', '', (string)($dados['serie_nfe'] ?? '001')) ?? '001', 3, '0', STR_PAD_LEFT), 0, 3),
            'proximo_numero_nfe' => max(1, (int)($dados['proximo_numero_nfe'] ?? 1)),
            'certificado_path' => trim((string)($dados['certificado_path'] ?? '')),
            'certificado_senha' => trim((string)($dados['certificado_senha'] ?? '')),
        ];
    }

    /**
     * Executa as validacoes do cadastro da empresa.
     *
     * @param array<string, mixed> $dados Dados normalizados.
     * @return string[]
     */
    private function validar(array $dados): array
    {
        $erros = [];

        if ($dados['nome'] === '') {
            $erros[] = 'Informe o nome da empresa.';
        }

        if (!$this->validarCnpj($dados['cnpj'])) {
            $erros[] = 'Informe um CNPJ valido.';
        }

        if ($dados['email'] !== '' && filter_var($dados['email'], FILTER_VALIDATE_EMAIL) === false) {
            $erros[] = 'Informe um e-mail valido.';
        }

        if ($dados['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf'])) {
            $erros[] = 'UF deve conter 2 letras.';
        }

        if ($dados['cep'] !== '' && strlen($dados['cep']) !== 8) {
            $erros[] = 'CEP deve conter 8 digitos.';
        }

        if ($dados['codigo_municipio'] !== '' && strlen($dados['codigo_municipio']) !== 7) {
            $erros[] = 'Codigo do municipio deve conter 7 digitos.';
        }

        if (!in_array($dados['regime_tributario'], ['1', '2', '3'], true)) {
            $erros[] = 'Regime tributario invalido.';
        }

        if (!in_array($dados['ambiente_nfe'], ['1', '2'], true)) {
            $erros[] = 'Ambiente da NF-e invalido.';
        }

        if ($dados['serie_nfe'] === '' || strlen($dados['serie_nfe']) > 3) {
            $erros[] = 'Serie da NF-e deve conter ate 3 digitos.';
        }

        return $erros;
    }

    /**
     * @param mixed $arquivo
     * @return string[]
     */
    private function validarArquivoCertificado(mixed $arquivo): array
    {
        if (!is_array($arquivo) || (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        $erro = (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro !== UPLOAD_ERR_OK) {
            return ['Nao foi possivel receber o certificado digital enviado.'];
        }

        $nome = (string)($arquivo['name'] ?? '');
        $extensao = strtolower((string)pathinfo($nome, PATHINFO_EXTENSION));
        if (!in_array($extensao, ['pfx', 'p12'], true)) {
            return ['Envie um certificado digital no formato .pfx ou .p12.'];
        }

        return [];
    }

    /**
     * @param mixed $arquivo
     * @return array{path: string|null, erro: string|null}
     */
    private function armazenarCertificado(mixed $arquivo): array
    {
        if (!is_array($arquivo) || (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'erro' => null];
        }

        $tmpName = (string)($arquivo['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['path' => null, 'erro' => 'Nao foi possivel validar o upload do certificado digital.'];
        }

        $extensao = strtolower((string)pathinfo((string)($arquivo['name'] ?? ''), PATHINFO_EXTENSION));
        $diretorio = dirname(__DIR__, 3) . '/storage/certificados';
        if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
            return ['path' => null, 'erro' => 'Nao foi possivel preparar a pasta do certificado digital.'];
        }

        $nomeArquivo = sprintf('certificado_%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(4)), $extensao);
        $destino = $diretorio . DIRECTORY_SEPARATOR . $nomeArquivo;

        if (!move_uploaded_file($tmpName, $destino)) {
            return ['path' => null, 'erro' => 'Nao foi possivel salvar o certificado digital enviado.'];
        }

        return ['path' => $destino, 'erro' => null];
    }

    /**
     * Valida um CNPJ numerico.
     */
    private function validarCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^' . $cnpj[0] . '{14}$/', $cnpj)) {
            return false;
        }

        $t = 12;
        $d = 0;
        $p = 5;

        for ($i = 0; $i < $t; $i++) {
            $d += (int)$cnpj[$i] * $p;
            $p = ($p === 2) ? 9 : $p - 1;
        }

        $d = ((10 * $d) % 11) % 10;
        if ((int)$cnpj[12] !== $d) {
            return false;
        }

        $t = 13;
        $d = 0;
        $p = 6;

        for ($i = 0; $i < $t; $i++) {
            $d += (int)$cnpj[$i] * $p;
            $p = ($p === 2) ? 9 : $p - 1;
        }

        $d = ((10 * $d) % 11) % 10;

        return (int)$cnpj[13] === $d;
    }
}