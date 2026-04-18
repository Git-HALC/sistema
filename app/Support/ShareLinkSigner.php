<?php

namespace App\Support;

final class ShareLinkSigner
{
    private static function secret(): string
    {
        $env = getenv('ORCAMENTO_SHARE_SECRET');
        if (is_string($env) && trim($env) !== '') {
            return $env;
        }

        return 'troque-esta-chave-orcamento-share-em-producao';
    }

    private static function payload(int $id, string $dataEmissao, float $valorTotal): string
    {
        return $id . '|' . $dataEmissao . '|' . number_format(round($valorTotal, 4), 4, '.', '');
    }

    public static function tokenOrcamento(int $id, string $dataEmissao, float $valorTotal): string
    {
        return hash_hmac('sha256', self::payload($id, $dataEmissao, $valorTotal), self::secret());
    }

    public static function validarTokenOrcamento(int $id, string $dataEmissao, float $valorTotal, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $esperado = self::tokenOrcamento($id, $dataEmissao, $valorTotal);
        return hash_equals($esperado, $token);
    }
}
