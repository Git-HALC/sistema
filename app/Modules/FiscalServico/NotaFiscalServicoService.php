<?php

declare(strict_types=1);

namespace App\Modules\FiscalServico;

use App\Support\AuditLogger;
use PDO;
use Throwable;
use TCPDF;

final class NotaFiscalServicoService
{
    private ?bool $servicoValorColumnExists = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly NotaFiscalServicoRepository $notaRepository,
        private readonly EmpresaFiscalServicoRepository $configRepository
    ) {
    }

    /**
     * @return array{sucesso: bool, aviso: ?string, erro: ?string, nota: ?NotaFiscalServico}
     */
    public function gerarNota(string $servicoId): array
    {
        $servico = $this->buscarServicoFaturado($servicoId);
        if ($servico === null) {
            return ['sucesso' => false, 'aviso' => null, 'erro' => 'Servico faturado nao encontrado.', 'nota' => null];
        }

        $itens = $this->filtrarItensServico($servico);
        if ($itens === []) {
            return ['sucesso' => false, 'aviso' => 'Servico sem valor tributavel para NFS-e.', 'erro' => null, 'nota' => null];
        }

        $existente = $this->notaRepository->buscarPorServicoId($servicoId);
        if ($existente !== null && in_array($existente->status, [NotaFiscalServico::STATUS_PENDENTE, NotaFiscalServico::STATUS_ENVIADA], true)) {
            return ['sucesso' => true, 'aviso' => 'NFS-e ja existente para este servico.', 'erro' => null, 'nota' => $existente];
        }

        $this->pdo->beginTransaction();

        try {
            $config = $this->configRepository->buscarConfiguracao();
            if ($config === null) {
                throw new NfseConfigException('Configure o emitente da NFS-e antes de gerar a nota.');
            }

            $numeroRps = $this->notaRepository->proximoNumeroRps();
            $nota = $this->montarNota($servicoId, $numeroRps);
            $this->notaRepository->criar($nota);
            $this->pdo->commit();

            $nota = $this->enviarParaPrefeitura((int)$nota->id);

            return ['sucesso' => true, 'aviso' => null, 'erro' => null, 'nota' => $nota];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->registrarErro($servicoId, $e, $servico);
            return ['sucesso' => false, 'aviso' => null, 'erro' => $e->getMessage(), 'nota' => null];
        }
    }

    public function enviarParaPrefeitura(int $nfseId): NotaFiscalServico
    {
        $nota = $this->notaRepository->buscarPorId($nfseId);
        if ($nota === null) {
            throw new NfseEnvioException('NFS-e nao encontrada para envio.');
        }

        $servico = $this->buscarServicoFaturado($nota->servico_id);
        if ($servico === null) {
            throw new NfseEnvioException('Servico da NFS-e nao encontrado.');
        }

        $config = $this->configRepository->buscarConfiguracao();
        if ($config === null) {
            throw new NfseConfigException('Configuracao fiscal de servico nao encontrada.');
        }

        $xml = $this->montarXmlRps($nota, $servico, $config);
        $url = $config->webserviceUrl();
        if ($url === null || $url === '') {
            throw new NfseConfigException('URL do webservice municipal nao configurada.');
        }

        $nota->xml_enviado = $xml;

        try {
            $retorno = str_contains(strtolower($url), 'soap')
                ? $this->enviarSoap($url, $xml, $config)
                : $this->enviarRest($url, $xml, $config);

            $nota->xml_retorno = $retorno;
            $dadosRetorno = $this->interpretarRetornoPrefeitura($retorno);
            $nota->numero_nfse = $dadosRetorno['numero_nfse'] ?? $nota->numero_nfse;
            $nota->codigo_verificacao = $dadosRetorno['codigo_verificacao'] ?? $nota->codigo_verificacao;
            $nota->status = ($dadosRetorno['sucesso'] ?? false) ? NotaFiscalServico::STATUS_ENVIADA : NotaFiscalServico::STATUS_ERRO;
            $nota->erro_mensagem = $dadosRetorno['erro'] ?? '';
            $this->notaRepository->atualizar($nota);

            $this->registrarAuditoria(
                $nota->servico_id,
                $nota->status === NotaFiscalServico::STATUS_ENVIADA ? 'EMITIR_NFSE' : 'ERRO_NFSE',
                $nota->status === NotaFiscalServico::STATUS_ENVIADA ? 'NFS-e enviada ao municipio.' : 'Falha ao enviar NFS-e.'
            );

            if ($nota->status !== NotaFiscalServico::STATUS_ENVIADA) {
                throw new NfseEnvioException($nota->erro_mensagem !== '' ? $nota->erro_mensagem : 'Nao foi possivel autorizar a NFS-e.');
            }

            return $nota;
        } catch (Throwable $e) {
            $nota->status = NotaFiscalServico::STATUS_ERRO;
            $nota->erro_mensagem = $this->sanitizarMensagemErro($e->getMessage(), $servico);
            $nota->xml_retorno = $nota->xml_retorno !== '' ? $nota->xml_retorno : '<erro>' . htmlspecialchars($nota->erro_mensagem, ENT_QUOTES, 'UTF-8') . '</erro>';
            $this->notaRepository->atualizar($nota);
            $this->registrarErro($nota->servico_id, $e, $servico);
            throw $e;
        }
    }

    public function gerarPdf(int $nfseId): string
    {
        $nota = $this->notaRepository->buscarPorId($nfseId);
        if ($nota === null) {
            throw new NotaFiscalServicoException('NFS-e nao encontrada.');
        }

        $servico = $this->buscarServicoFaturado($nota->servico_id);
        if ($servico === null) {
            throw new NotaFiscalServicoException('Servico vinculado nao encontrado.');
        }

        $pdf = new TCPDF();
        $pdf->SetCreator('Sistema DM');
        $pdf->SetAuthor('Sistema DM');
        $pdf->SetTitle('NFS-e ' . ($nota->numero_nfse ?? 'PENDENTE'));
        $pdf->AddPage();
        $html = '
            <h1>NFS-e</h1>
            <p><strong>Numero:</strong> ' . htmlspecialchars((string)($nota->numero_nfse ?? 'Pendente'), ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>RPS:</strong> ' . htmlspecialchars((string)$nota->numero_rps, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Status:</strong> ' . htmlspecialchars($nota->status, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Servico:</strong> ' . htmlspecialchars((string)($servico['servico_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Tomador:</strong> ' . htmlspecialchars((string)($servico['cliente_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Valor:</strong> R$ ' . number_format((float)($servico['servico_valor'] ?? 0), 2, ',', '.') . '</p>
        ';
        $pdf->writeHTML($html);

        return (string)$pdf->Output('', 'S');
    }

    public function enviarEmail(int $nfseId): bool
    {
        $nota = $this->notaRepository->buscarPorId($nfseId);
        if ($nota === null) {
            throw new NotaFiscalServicoException('NFS-e nao encontrada.');
        }

        $servico = $this->buscarServicoFaturado($nota->servico_id);
        if ($servico === null) {
            throw new NotaFiscalServicoException('Servico vinculado nao encontrado.');
        }

        $email = trim((string)($servico['cliente_email'] ?? ''));
        if ($email === '') {
            throw new NotaFiscalServicoException('Cliente sem e-mail cadastrado para envio da NFS-e.');
        }

        $pdf = $this->gerarPdf($nfseId);
        $boundary = '=_nfse_' . bin2hex(random_bytes(12));
        $assunto = 'NFS-e do servico #' . (string)($servico['numero'] ?? $servico['servico_id'] ?? '');
        $corpoTexto = "Segue em anexo a sua NFS-e.\r\n";
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];
        $mensagem = "--{$boundary}\r\n";
        $mensagem .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $mensagem .= $corpoTexto . "\r\n";
        $mensagem .= "--{$boundary}\r\n";
        $mensagem .= "Content-Type: application/pdf; name=\"nfse.pdf\"\r\n";
        $mensagem .= "Content-Transfer-Encoding: base64\r\n";
        $mensagem .= "Content-Disposition: attachment; filename=\"nfse.pdf\"\r\n\r\n";
        $mensagem .= chunk_split(base64_encode($pdf)) . "\r\n";
        $mensagem .= "--{$boundary}--";

        return mail($email, $assunto, $mensagem, implode("\r\n", $headers));
    }

    public function cancelarNota(int $nfseId): NotaFiscalServico
    {
        $nota = $this->notaRepository->buscarPorId($nfseId);
        if ($nota === null) {
            throw new NfseCancelamentoException('NFS-e nao encontrada.');
        }

        if ($nota->status !== NotaFiscalServico::STATUS_ENVIADA) {
            throw new NfseCancelamentoException('Somente NFS-e enviada pode ser cancelada.');
        }

        $config = $this->configRepository->buscarConfiguracao();
        if ($config === null) {
            throw new NfseConfigException('Configuracao fiscal de servico nao encontrada.');
        }

        $url = $config->webserviceUrl();
        if ($url === null || $url === '') {
            throw new NfseConfigException('URL do webservice municipal nao configurada.');
        }

        $payload = '<CancelarNfse><Numero>' . htmlspecialchars((string)$nota->numero_nfse, ENT_QUOTES, 'UTF-8') . '</Numero></CancelarNfse>';
        $retorno = str_contains(strtolower($url), 'soap')
            ? $this->enviarSoap($url, $payload, $config)
            : $this->enviarRest($url, $payload, $config);

        $dadosRetorno = $this->interpretarRetornoPrefeitura($retorno);
        if (!($dadosRetorno['sucesso'] ?? false)) {
            throw new NfseCancelamentoException((string)($dadosRetorno['erro'] ?? 'Falha ao cancelar NFS-e.'));
        }

        $nota->status = NotaFiscalServico::STATUS_CANCELADA;
        $nota->xml_retorno = $retorno;
        $nota->erro_mensagem = '';
        $this->notaRepository->atualizar($nota);
        $this->registrarAuditoria($nota->servico_id, 'CANCELAR_NFSE', 'NFS-e cancelada com sucesso.');

        return $nota;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarServicoDetalhado(string $servicoId): ?array
    {
        return $this->buscarServicoFaturado($servicoId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarServicoFaturado(string $servicoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                s.*,
                ' . $this->servicoValorSelectExpression() . ' AS servico_valor,
                c.nome AS cliente_nome,
                c.cpf_cnpj AS cliente_documento,
                c.email AS cliente_email,
                c.telefone AS cliente_telefone
             FROM servicos s
             LEFT JOIN clientes c ON c.id = s.cliente_id
             WHERE s.id = :id
               AND s.status = :status
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $servicoId,
            ':status' => 'FATURADO',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $servico
     * @return list<array<string, mixed>>
     */
    private function filtrarItensServico(array $servico): array
    {
        $descricao = trim((string)($servico['servico_nome'] ?? ''));
        $valor = (float)($servico['servico_valor'] ?? 0);

        if ($descricao === '' || $valor <= 0) {
            return [];
        }

        return [[
            'descricao' => $descricao,
            'valor' => $valor,
        ]];
    }

    private function montarNota(string $servicoId, int $numeroRps): NotaFiscalServico
    {
        $nota = new NotaFiscalServico();
        $nota->servico_id = $servicoId;
        $nota->numero_rps = $numeroRps;
        $nota->status = NotaFiscalServico::STATUS_PENDENTE;

        return $nota;
    }

    /**
     * @param array<string, mixed> $servico
     */
    private function montarXmlRps(NotaFiscalServico $nota, array $servico, EmpresaFiscalServico $config): string
    {
        $valor = number_format((float)($servico['servico_valor'] ?? 0), 2, '.', '');
        $aliquota = number_format($config->aliquota_iss_padrao, 4, '.', '');
        $iss = number_format(((float)($servico['servico_valor'] ?? 0) * $config->aliquota_iss_padrao) / 100, 2, '.', '');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<GerarNfseEnvio>'
            . '<Rps>'
            . '<IdentificacaoRps><Numero>' . $nota->numero_rps . '</Numero><Serie>RPS</Serie><Tipo>1</Tipo></IdentificacaoRps>'
            . '<Prestador><Cnpj>' . htmlspecialchars($config->cnpj, ENT_QUOTES, 'UTF-8') . '</Cnpj><InscricaoMunicipal>' . htmlspecialchars($config->inscricao_municipal, ENT_QUOTES, 'UTF-8') . '</InscricaoMunicipal></Prestador>'
            . '<Tomador><RazaoSocial>' . htmlspecialchars((string)($servico['cliente_nome'] ?? 'Consumidor Final'), ENT_QUOTES, 'UTF-8') . '</RazaoSocial><CpfCnpj>' . htmlspecialchars((string)($servico['cliente_documento'] ?? ''), ENT_QUOTES, 'UTF-8') . '</CpfCnpj></Tomador>'
            . '<Servico><Valores><ValorServicos>' . $valor . '</ValorServicos><ValorIss>' . $iss . '</ValorIss><Aliquota>' . $aliquota . '</Aliquota></Valores>'
            . '<ItemListaServico>1401</ItemListaServico>'
            . '<CodigoMunicipio>' . htmlspecialchars($config->codigo_municipio_ibge, ENT_QUOTES, 'UTF-8') . '</CodigoMunicipio>'
            . '<Discriminacao>' . htmlspecialchars((string)($servico['servico_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</Discriminacao></Servico>'
            . '</Rps>'
            . '</GerarNfseEnvio>';
    }

    private function enviarSoap(string $url, string $xml, EmpresaFiscalServico $config): string
    {
        return $this->enviarHttp($url, $xml, $config, [
            'Content-Type: text/xml; charset=UTF-8',
            'SOAPAction: ""',
        ]);
    }

    private function enviarRest(string $url, string $xml, EmpresaFiscalServico $config): string
    {
        return $this->enviarHttp($url, $xml, $config, [
            'Content-Type: application/xml; charset=UTF-8',
            'Accept: application/xml',
        ]);
    }

    /**
     * @param list<string> $headers
     */
    private function enviarHttp(string $url, string $xml, EmpresaFiscalServico $config, array $headers): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NfseEnvioException('Nao foi possivel inicializar a conexao com a prefeitura.');
        }

        if ($config->usuario_webservice !== null && $config->senha_webservice !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $config->usuario_webservice . ':' . $config->senha_webservice);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new NfseEnvioException('Falha na comunicacao com a prefeitura: ' . $error);
        }

        if ($status >= 400) {
            throw new NfseEnvioException('Prefeitura retornou HTTP ' . $status . '.');
        }

        return (string)$response;
    }

    /**
     * @return array{sucesso: bool, numero_nfse?: string, codigo_verificacao?: string, erro?: string}
     */
    private function interpretarRetornoPrefeitura(string $xml): array
    {
        $sucesso = preg_match('/<Sucesso>\s*(true|1)\s*<\/Sucesso>/i', $xml) === 1
            || preg_match('/<NumeroNfse>([^<]+)<\/NumeroNfse>/i', $xml) === 1
            || preg_match('/<Numero>([^<]+)<\/Numero>/i', $xml) === 1;

        preg_match('/<NumeroNfse>([^<]+)<\/NumeroNfse>/i', $xml, $numeroNfse);
        preg_match('/<Numero>([^<]+)<\/Numero>/i', $xml, $numeroGenerico);
        preg_match('/<CodigoVerificacao>([^<]+)<\/CodigoVerificacao>/i', $xml, $codigo);
        preg_match('/<Mensagem>([^<]+)<\/Mensagem>/i', $xml, $erro);

        return [
            'sucesso' => $sucesso,
            'numero_nfse' => $numeroNfse[1] ?? $numeroGenerico[1] ?? null,
            'codigo_verificacao' => $codigo[1] ?? null,
            'erro' => $sucesso ? null : trim((string)($erro[1] ?? 'Retorno municipal nao confirmou a autorizacao da NFS-e.')),
        ];
    }

    /**
     * @param array<string, mixed>|null $servico
     */
    private function registrarErro(string $referencia, Throwable $e, ?array $servico = null): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->registrar(
            'fiscal_servico',
            'ERRO',
            'nfse',
            null,
            'Erro no fluxo da NFS-e.',
            [
                'referencia' => $referencia,
                'erro' => $this->sanitizarMensagemErro($e->getMessage(), $servico),
            ]
        );
    }

    private function registrarAuditoria(string $servicoId, string $acao, string $descricao): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->registrar('fiscal_servico', $acao, 'servico', null, $descricao, [
            'servico_id' => $servicoId,
        ]);
    }

    /**
     * @param array<string, mixed>|null $servico
     */
    private function sanitizarMensagemErro(string $mensagem, ?array $servico = null): string
    {
        $sanitizada = $mensagem;

        if ($servico !== null) {
            $documento = trim((string)($servico['cliente_documento'] ?? ''));
            $email = trim((string)($servico['cliente_email'] ?? ''));

            if ($documento !== '') {
                $sanitizada = str_replace($documento, '[doc:' . substr(hash('sha256', $documento), 0, 12) . ']', $sanitizada);
            }

            if ($email !== '') {
                $sanitizada = str_replace($email, '[email:' . substr(hash('sha256', $email), 0, 12) . ']', $sanitizada);
            }
        }

        return $sanitizada;
    }

    private function servicoValorSelectExpression(): string
    {
        return $this->hasServicoValorColumn() ? 's.servico_valor' : 's.valor_total';
    }

    private function hasServicoValorColumn(): bool
    {
        if ($this->servicoValorColumnExists !== null) {
            return $this->servicoValorColumnExists;
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1
               FROM information_schema.columns
              WHERE table_schema = 'public'
                AND table_name = 'servicos'
                AND column_name = 'servico_valor'
              LIMIT 1"
        );
        $stmt->execute();

        return $this->servicoValorColumnExists = (bool)$stmt->fetchColumn();
    }
}
