<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * Testa conectividade com a SEFAZ lendo o status do serviço.
 * Usa sped-nfe quando certificado está configurado; fallback pra cURL nos endpoints públicos.
 */
final class SefazStatusTester
{
    public function testar(string $uf, int $ambiente): array
    {
        $uf = strtoupper($uf);

        $configJson = json_encode([
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => $ambiente,
            'razaosocial' => 'TESTE',
            'siglaUF' => $uf,
            'cnpj' => '00000000000000',
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
            'tokenIBPT' => '',
            'CSC' => '',
            'CSCid' => '',
            'proxyConf' => ['proxyIp' => '', 'proxyPort' => '', 'proxyUser' => '', 'proxyPass' => ''],
        ]);

        try {
            // tenta via sped-nfe (sem certificado, apenas ping)
            $tools = new Tools($configJson, new Certificate('', ''));
            $tools->model('65');
            $resp = $tools->sefazStatus($uf);
            $xml = @simplexml_load_string($resp);
            if ($xml) {
                return [
                    'cStat' => (string)$xml->cStat,
                    'xMotivo' => (string)$xml->xMotivo,
                    'dhRecbto' => (string)$xml->dhRecbto,
                ];
            }
            return ['raw' => substr((string)$resp, 0, 400)];
        } catch (\Throwable $e) {
            throw new RuntimeException('Falha ao contatar SEFAZ: ' . $e->getMessage());
        }
    }
}
