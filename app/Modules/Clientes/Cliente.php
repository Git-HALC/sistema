<?php

namespace App\Modules\Clientes;

/**
 * Model: Cliente
 *
 * Representa um cliente cadastrado no sistema.
 * Contém apenas estado — sem acesso a banco.
 */
class Cliente
{
    public int     $id            = 0;
    public string  $nome          = '';
    public string  $cpfCnpj       = ''; // novo campo CPF/CNPJ
    public string  $email         = '';
    public ?string $telefone      = null;
    public bool    $ehCliente     = true;
    public bool    $ehFornecedor  = false;
    public ?string $logradouro    = null;
    public ?string $numeroEndereco = null;
    public ?string $complemento   = null;
    public ?string $bairro        = null;
    public ?string $cidade        = null;
    public ?string $estado        = null;
    public ?string $cep           = null;
    public ?string $codigoMunicipio = null;
    public ?string $ie            = null;
    public string  $indIeDest     = '9';
    public int     $prazoFaturamentoDias = 0;
    public bool    $ativo         = true;
    public string  $createdAt     = '';
    public string  $updatedAt     = '';

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function fromArray(array $row): self
    {
        $c               = new self();
        $c->id           = (int)   ($row['id']            ?? 0);
        $c->nome         = (string)($row['nome']          ?? '');
        $c->cpfCnpj      = (string)($row['cpf_cnpj']      ?? '');
        $c->email        = (string)($row['email']         ?? '');
        $c->telefone     = $row['telefone']               ?? null;
        $c->ehCliente    = filter_var($row['eh_cliente'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $c->ehFornecedor = filter_var($row['eh_fornecedor'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $c->logradouro   = $row['logradouro']             ?? null;
        $c->numeroEndereco = $row['numero_endereco']      ?? null;
        $c->complemento  = $row['complemento']            ?? null;
        $c->bairro       = $row['bairro']                 ?? null;
        $c->cidade       = $row['cidade']                 ?? null;
        $c->estado       = $row['estado']                 ?? null;
        $c->cep          = $row['cep']                    ?? null;
        $c->codigoMunicipio = $row['codigo_municipio']    ?? null;
        $c->ie           = $row['ie']                     ?? null;
        $c->indIeDest    = (string)($row['ind_ie_dest']   ?? '9');
        $c->prazoFaturamentoDias = (int)($row['prazo_faturamento_dias'] ?? 0);
        $c->ativo        = (bool)  ($row['ativo']         ?? true);
        $c->createdAt    = $row['created_at']             ?? '';
        $c->updatedAt    = $row['updated_at']             ?? '';

        return $c;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isCNPJ(): bool
    {
        $cnpj = preg_replace('/\D/', '', $this->cpfCnpj);
        return strlen($cnpj) === 14;
    }

    public function isCPF(): bool
    {
        $cpf = preg_replace('/\D/', '', $this->cpfCnpj);
        return strlen($cpf) === 11;
    }

    public function getCpfCnpjFormatado(): string
    {
        $valor = preg_replace('/\D/', '', $this->cpfCnpj);

        if (strlen($valor) === 11) {
            // CPF: xxx.xxx.xxx-xx
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $valor);
        } elseif (strlen($valor) === 14) {
            // CNPJ: xx.xxx.xxx/xxxx-xx
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $valor);
        }

        return $this->cpfCnpj;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'nome'      => $this->nome,
            'cpf_cnpj'  => $this->cpfCnpj,
            'email'     => $this->email,
            'telefone'  => $this->telefone,
            'eh_cliente' => $this->ehCliente,
            'eh_fornecedor' => $this->ehFornecedor,
            'logradouro' => $this->logradouro,
            'numero_endereco' => $this->numeroEndereco,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cidade'    => $this->cidade,
            'estado'    => $this->estado,
            'cep' => $this->cep,
            'codigo_municipio' => $this->codigoMunicipio,
            'ie' => $this->ie,
            'ind_ie_dest' => $this->indIeDest,
            'prazo_faturamento_dias' => $this->prazoFaturamentoDias,
            'ativo'     => $this->ativo,
        ];
    }
}
