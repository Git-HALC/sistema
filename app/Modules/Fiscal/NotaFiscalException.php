<?php

namespace App\Modules\Fiscal;

use RuntimeException;

/**
 * Exceção de negócio do módulo fiscal.
 */
class NotaFiscalException extends RuntimeException
{
    /**
     * @param string[] $erros
     */
    public static function fromErrors(array $erros): self
    {
        return new self(implode(' | ', $erros));
    }
}
