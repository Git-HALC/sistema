<?php

namespace App\Modules\Usuarios;

/**
 * Model: Usuario
 *
 * Representa um usuário do sistema.
 * Contém apenas estado — sem acesso a banco.
 */
class Usuario
{
    public int     $id            = 0;
    public string  $nome          = '';
    public string  $email         = '';
    public ?string $telefone      = null;
    public int     $nivelAcessoId = 2;
    public bool    $ativo         = true;
    public ?string $ultimoAcesso  = null;
    public string  $createdAt     = '';

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function fromArray(array $row): self
    {
        $u               = new self();
        $u->id           = (int)   ($row['id']               ?? 0);
        $u->nome         = (string) ($row['nome']            ?? '');
        $u->email        = (string) ($row['email']           ?? '');
        $u->telefone     = $row['telefone']                  ?? null;
        $u->nivelAcessoId = (int)   ($row['nivel_acesso_id'] ?? 2);
        $u->ativo        = (bool)   ($row['ativo']           ?? true);
        $u->ultimoAcesso = $row['ultimo_acesso']             ?? null;
        $u->createdAt    = $row['created_at']                ?? '';

        return $u;
    }

    // -------------------------------------------------------------------------
    // Helpers de exibição
    // -------------------------------------------------------------------------

    public function nivelLabel(): string
    {
        return match ($this->nivelAcessoId) {
            1 => 'Administrador',
            2 => 'Suporte',
            3 => 'Financeiro',
            4 => 'Personalizado',
            default => "Nível {$this->nivelAcessoId}",
        };
    }
}
