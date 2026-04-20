<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use PDO;

final class PerfilTributarioService
{
    private PerfilTributarioRepository $repo;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repo = new PerfilTributarioRepository($pdo);
    }

    public function garantirPerfil(): int
    {
        return $this->repo->criarPerfilSeNaoExiste(1);
    }

    public function obterPerfilCompleto(): array
    {
        $id = $this->garantirPerfil();
        $perfil = $this->repo->obterPerfil(1) ?? [];
        $empresa = $this->obterEmpresaLocal();
        // Sincroniza ambiente/serie/numero NFC-e a partir de empresa_local (fonte unica)
        if ($empresa) {
            $sync = [
                'ambiente_nfce' => (int)($empresa['ambiente_nfe'] ?? 2),
                'serie_nfce' => (int)($empresa['serie_nfe'] ?? 1),
                'numero_nfce_atual' => (int)($empresa['proximo_numero_nfe'] ?? 0),
            ];
            $mudou = false;
            foreach ($sync as $k => $v) {
                if ((int)($perfil[$k] ?? -1) !== $v) { $mudou = true; break; }
            }
            if ($mudou) {
                $this->repo->atualizarPerfil($id, $sync);
                $perfil = array_merge($perfil, $sync);
            }
        }
        return [
            'perfil' => $perfil,
            'empresa' => $empresa,
            'tributacao' => $this->repo->listarTributacaoPorEstado($id),
            'servicos' => $this->repo->listarServicosComConfig(),
        ];
    }

    public function salvarDadosEmpresa(array $dados): void
    {
        $cols = ['cnpj','nome','inscricao_estadual','inscricao_municipal','codigo_municipio',
                 'logradouro','numero','complemento','bairro','cidade','uf','cep','regime_tributario'];
        $sets = [];
        $params = [':id' => 1];
        foreach ($cols as $c) {
            if (array_key_exists($c, $dados)) {
                $sets[] = "$c = :$c";
                $params[":$c"] = $dados[$c] !== '' ? $dados[$c] : null;
            }
        }
        if (!$sets) return;
        $sql = 'UPDATE empresa_local SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);

        if (!empty($dados['regime_tributario'])) {
            $this->repo->atualizarPerfil($this->garantirPerfil(), [
                'regime_tributario' => (string)$dados['regime_tributario'],
            ]);
        }
    }

    public function salvarConfigNfce(array $dados): void
    {
        $id = $this->garantirPerfil();
        // Ambiente/serie/numero sao espelhados automaticamente de empresa_local
        $emp = $this->obterEmpresaLocal();
        $this->repo->atualizarPerfil($id, [
            'csc_id_homologacao' => $dados['csc_id_homologacao'] ?? null,
            'csc_token_homologacao' => $dados['csc_token_homologacao'] ?? null,
            'csc_id_producao' => $dados['csc_id_producao'] ?? null,
            'csc_token_producao' => $dados['csc_token_producao'] ?? null,
            'ambiente_nfce' => (int)($emp['ambiente_nfe'] ?? 2),
            'serie_nfce' => (int)($emp['serie_nfe'] ?? 1),
            'numero_nfce_atual' => (int)($emp['proximo_numero_nfe'] ?? 0),
        ]);
    }

    public function salvarConfigNfse(array $dados, array $arquivos = []): void
    {
        $id = $this->garantirPerfil();
        $atual = $this->repo->obterPerfil(1);

        $senhaCifrada = null;
        if (!empty($dados['nfse_senha'])) {
            $senhaCifrada = FiscalCrypto::encrypt((string)$dados['nfse_senha']);
        } elseif (!empty($dados['manter_senha'])) {
            $senhaCifrada = $atual['nfse_senha_cifrada'] ?? null;
        }

        $certSenhaCifrada = null;
        if (!empty($dados['nfse_certificado_senha'])) {
            $certSenhaCifrada = FiscalCrypto::encrypt((string)$dados['nfse_certificado_senha']);
        } elseif (!empty($dados['manter_cert_senha'])) {
            $certSenhaCifrada = $atual['nfse_certificado_senha_cifrada'] ?? null;
        }

        // Upload do certificado .pfx (se veio arquivo)
        $certPath = $atual['nfse_certificado_path'] ?? null;
        $up = $arquivos['nfse_certificado_file'] ?? null;
        if ($up && isset($up['error']) && $up['error'] === UPLOAD_ERR_OK && !empty($up['tmp_name'])) {
            $ext = strtolower(pathinfo((string)$up['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pfx') {
                throw new \RuntimeException('Apenas arquivos .pfx sao aceitos.');
            }
            if ((int)($up['size'] ?? 0) > 2_000_000) {
                throw new \RuntimeException('Arquivo .pfx excede 2 MB.');
            }
            $destDir = realpath(__DIR__ . '/../../../storage') ?: (__DIR__ . '/../../../storage');
            $destDir = $destDir . DIRECTORY_SEPARATOR . 'fiscal' . DIRECTORY_SEPARATOR . 'certificados';
            if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
            $safeName = 'nfse_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pfx';
            $destFull = $destDir . DIRECTORY_SEPARATOR . $safeName;
            if (!@move_uploaded_file((string)$up['tmp_name'], $destFull)) {
                throw new \RuntimeException('Falha ao mover o certificado para ' . $destFull);
            }
            // remove arquivo antigo (se dentro da pasta de certificados)
            if ($certPath && is_file($certPath) && str_contains((string)$certPath, 'fiscal' . DIRECTORY_SEPARATOR . 'certificados')) {
                @unlink($certPath);
            }
            $certPath = $destFull;
        }

        $this->repo->atualizarPerfil($id, [
            'nfse_modo_auth' => (string)($dados['nfse_modo_auth'] ?? 'usuario_senha'),
            'nfse_usuario' => $dados['nfse_usuario'] ?? null,
            'nfse_senha_cifrada' => $senhaCifrada,
            'nfse_certificado_path' => $certPath,
            'nfse_certificado_senha_cifrada' => $certSenhaCifrada,
            'serie_rps' => (string)($dados['serie_rps'] ?? 'RPS'),
            'numero_rps_atual' => (int)($dados['numero_rps_atual'] ?? 0),
            'ambiente_nfse' => (string)($dados['ambiente_nfse'] ?? 'homologacao'),
        ]);
    }

    /**
     * Salva em lote todas as alíquotas da matriz (27 UFs).
     * @param array<int, array<string,mixed>> $linhas
     */
    public function salvarTributacaoLote(array $linhas): int
    {
        $id = $this->garantirPerfil();
        $emp = $this->obterEmpresaLocal();
        $ufOrigem = strtoupper((string)($emp['uf'] ?? 'SP'));
        return $this->repo->salvarTributacaoLote($id, $ufOrigem, $linhas);
    }

    public function salvarConfigServicos(array $linhas): int
    {
        $n = 0;
        foreach ($linhas as $l) {
            $sid = (int)($l['servico_id'] ?? 0);
            if ($sid <= 0) continue;
            if (empty($l['codigo_lc116'])) continue;
            $this->repo->salvarConfigServico($sid, $l);
            $n++;
        }
        return $n;
    }

    private function obterEmpresaLocal(): array
    {
        $s = $this->pdo->query('SELECT * FROM empresa_local WHERE id = 1 LIMIT 1');
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
