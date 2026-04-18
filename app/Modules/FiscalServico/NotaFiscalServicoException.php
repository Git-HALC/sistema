<?php

declare(strict_types=1);

namespace App\Modules\FiscalServico;

use RuntimeException;

class NotaFiscalServicoException extends RuntimeException
{
}

final class NfseEnvioException extends NotaFiscalServicoException
{
}

final class NfseCancelamentoException extends NotaFiscalServicoException
{
}

final class NfseXmlException extends NotaFiscalServicoException
{
}

final class NfseConfigException extends NotaFiscalServicoException
{
}
