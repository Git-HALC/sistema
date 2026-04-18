<?php

namespace App\Modules\Financeiro;

use RuntimeException;

class NfeVinculadaException extends RuntimeException
{
    public function __construct(
        private readonly string $numeroNfe,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : 'Não é possível excluir: este lançamento está vinculado à NF-e autorizada nº ' . $numeroNfe . '. Cancele a NF-e antes de excluir.',
            $code,
            $previous
        );
    }

    public function getNumeroNfe(): string
    {
        return $this->numeroNfe;
    }
}
