<?php

namespace App\Modules\Usuarios;

/**
 * UsuarioService - casos de uso do modulo de Usuarios.
 */
class UsuarioService
{
    public function __construct(
        private readonly UsuarioRepository $repo
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
            'usuarios'     => $this->repo->listar($pagina, $porPagina),
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
        $erros = $this->validar($dados);

        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        $hash = password_hash($dados['senha'], PASSWORD_BCRYPT);

        try {
            $id = $this->repo->criar(
                trim($dados['nome']),
                strtolower(trim($dados['email'])),
                $hash,
                trim($dados['telefone'] ?? '') ?: null,
                (int) $dados['nivel_acesso_id'],
                (bool) ($dados['ativo'] ?? true)
            );
            return ['ok' => true, 'id' => $id];
        } catch (\Throwable $e) {
            error_log('UsuarioService::criar - ' . $e->getMessage());
            return ['ok' => false, 'erros' => ['Erro interno ao salvar usuario.']];
        }
    }

    // =========================================================================
    // Atualizar
    // =========================================================================

    /** @return array{ok: bool, erros?: string[]} */
    public function atualizar(int $id, array $dados): array
    {
        if ($this->repo->isAdminPadrao($id)) {
            $dados['email'] = UsuarioRepository::DEFAULT_ADMIN_EMAIL;
            $dados['nivel_acesso_id'] = 1;
            $dados['ativo'] = 1;
        }

        $erros = $this->validarEdicao($id, $dados);
        if (!empty($erros)) return ['ok' => false, 'erros' => $erros];

        $senhaHash = !empty($dados['senha'])
            ? password_hash($dados['senha'], PASSWORD_BCRYPT)
            : null;

        try {
            $this->repo->atualizar(
                $id,
                trim($dados['nome']),
                strtolower(trim($dados['email'])),
                $senhaHash,
                trim($dados['telefone'] ?? '') ?: null,
                (int) $dados['nivel_acesso_id'],
                (bool) ($dados['ativo'] ?? false)
            );
            return ['ok' => true];
        } catch (\Throwable $e) {
            error_log('UsuarioService::atualizar - ' . $e->getMessage());
            return ['ok' => false, 'erros' => ['Erro interno ao atualizar usuario.']];
        }
    }

    // =========================================================================
    // Excluir
    // =========================================================================

    /** @return array{ok: bool, erros?: string[]} */
    public function excluir(int $id, int $usuarioLogadoId): array
    {
        if ($id === $usuarioLogadoId) {
            return ['ok' => false, 'erros' => ["N\u{00E3}o \u{00E9} poss\u{00ED}vel excluir o pr\u{00F3}prio usu\u{00E1}rio."]];
        }

        if ($this->repo->isAdminPadrao($id)) {
            return ['ok' => false, 'erros' => ["O admin padr\u{00E3}o do sistema n\u{00E3}o pode ser exclu\u{00ED}do."]];
        }

        try {
            $this->repo->excluir($id);
            return ['ok' => true];
        } catch (\Throwable $e) {
            error_log('UsuarioService::excluir - ' . $e->getMessage());
            return ['ok' => false, 'erros' => ['Erro ao excluir usuario.']];
        }
    }

    // =========================================================================
    // Validacoes privadas
    // =========================================================================

    /** @return string[] */
    private function validar(array $d): array
    {
        $erros = [];

        $nome  = trim($d['nome']  ?? '');
        $email = trim($d['email'] ?? '');
        $senha = (string) ($d['senha'] ?? '');
        $conf  = (string) ($d['confirmar_senha'] ?? '');
        $nivel = (int)    ($d['nivel_acesso_id'] ?? 0);

        if ($nome === '') {
            $erros[] = 'O <strong>nome</strong> e obrigatorio.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O <strong>e-mail</strong> informado e invalido.';
        } elseif ($this->repo->emailExiste($email)) {
            $erros[] = 'Ja existe um usuario cadastrado com este <strong>e-mail</strong>.';
        }

        if (strlen($senha) < 6) {
            $erros[] = 'A <strong>senha</strong> deve ter pelo menos 6 caracteres.';
        }

        if ($senha !== $conf) {
            $erros[] = 'A <strong>confirmacao de senha</strong> nao confere.';
        }

        if (!in_array($nivel, [1, 2, 3, 4], true)) {
            $erros[] = '<strong>Nivel de acesso</strong> invalido.';
        }

        return $erros;
    }

    /** @return string[] */
    private function validarEdicao(int $id, array $d): array
    {
        $erros = [];

        $nome  = trim($d['nome']  ?? '');
        $email = trim($d['email'] ?? '');
        $senha = (string) ($d['senha'] ?? '');
        $conf  = (string) ($d['confirmar_senha'] ?? '');
        $nivel = (int)    ($d['nivel_acesso_id'] ?? 0);

        if ($nome === '') {
            $erros[] = 'O <strong>nome</strong> e obrigatorio.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O <strong>e-mail</strong> informado e invalido.';
        } elseif ($this->repo->emailExiste($email, $id)) {
            $erros[] = 'Ja existe outro usuario com este <strong>e-mail</strong>.';
        }

        if ($senha !== '') {
            if (strlen($senha) < 6) {
                $erros[] = 'A <strong>nova senha</strong> deve ter pelo menos 6 caracteres.';
            }
            if ($senha !== $conf) {
                $erros[] = 'A <strong>confirmacao de senha</strong> nao confere.';
            }
        }

        if (!in_array($nivel, [1, 2, 3, 4], true)) {
            $erros[] = '<strong>Nivel de acesso</strong> invalido.';
        }

        return $erros;
    }
}
