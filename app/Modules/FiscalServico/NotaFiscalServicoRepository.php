<?php

declare(strict_types=1);

namespace App\Modules\FiscalServico;

use PDO;
use RuntimeException;

final class NotaFiscalServicoRepository
{
    private ?bool $servicoValorColumnExists = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function criar(NotaFiscalServico $nota): NotaFiscalServico
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO nota_fiscal_servico (
                servico_id, numero_nfse, numero_rps, codigo_verificacao, xml_enviado,
                xml_retorno, pdf_path, status, erro_mensagem, created_at, updated_at
            ) VALUES (
                :servico_id, :numero_nfse, :numero_rps, :codigo_verificacao, :xml_enviado,
                :xml_retorno, :pdf_path, :status, :erro_mensagem, NOW(), NOW()
            )'
        );

        $stmt->execute([
            ':servico_id' => $nota->servico_id,
            ':numero_nfse' => $nota->numero_nfse,
            ':numero_rps' => $nota->numero_rps,
            ':codigo_verificacao' => $nota->codigo_verificacao,
            ':xml_enviado' => $nota->xml_enviado !== '' ? $nota->xml_enviado : null,
            ':xml_retorno' => $nota->xml_retorno !== '' ? $nota->xml_retorno : null,
            ':pdf_path' => $nota->pdf_path !== '' ? $nota->pdf_path : null,
            ':status' => $nota->status,
            ':erro_mensagem' => $nota->erro_mensagem !== '' ? $nota->erro_mensagem : null,
        ]);

        $nota->id = (int)$this->pdo->lastInsertId();
        return $nota;
    }

    public function atualizar(NotaFiscalServico $nota): bool
    {
        if ($nota->id === null) {
            throw new RuntimeException('Nao e possivel atualizar uma NFS-e sem ID.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE nota_fiscal_servico
             SET servico_id = :servico_id,
                 numero_nfse = :numero_nfse,
                 numero_rps = :numero_rps,
                 codigo_verificacao = :codigo_verificacao,
                 xml_enviado = :xml_enviado,
                 xml_retorno = :xml_retorno,
                 pdf_path = :pdf_path,
                 status = :status,
                 erro_mensagem = :erro_mensagem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            ':id' => $nota->id,
            ':servico_id' => $nota->servico_id,
            ':numero_nfse' => $nota->numero_nfse,
            ':numero_rps' => $nota->numero_rps,
            ':codigo_verificacao' => $nota->codigo_verificacao !== '' ? $nota->codigo_verificacao : null,
            ':xml_enviado' => $nota->xml_enviado !== '' ? $nota->xml_enviado : null,
            ':xml_retorno' => $nota->xml_retorno !== '' ? $nota->xml_retorno : null,
            ':pdf_path' => $nota->pdf_path !== '' ? $nota->pdf_path : null,
            ':status' => $nota->status,
            ':erro_mensagem' => $nota->erro_mensagem !== '' ? $nota->erro_mensagem : null,
        ]);
    }

    public function buscarPorId(int $id): ?NotaFiscalServico
    {
        $stmt = $this->pdo->prepare('SELECT * FROM nota_fiscal_servico WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? NotaFiscalServico::fromArray($row) : null;
    }

    public function buscarPorServicoId(string $servicoId): ?NotaFiscalServico
    {
        $stmt = $this->pdo->prepare('SELECT * FROM nota_fiscal_servico WHERE servico_id = :servico_id LIMIT 1');
        $stmt->execute([':servico_id' => $servicoId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? NotaFiscalServico::fromArray($row) : null;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return list<array<string, mixed>>
     */
    public function listarTodas(array $filtros = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['status'])) {
            $where[] = 'nfs.status = :status';
            $params[':status'] = (string)$filtros['status'];
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = 'nfs.created_at::date >= :data_inicio';
            $params[':data_inicio'] = (string)$filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = 'nfs.created_at::date <= :data_fim';
            $params[':data_fim'] = (string)$filtros['data_fim'];
        }

        $sql = '
            SELECT
                nfs.*,
                s.numero AS servico_numero,
                s.servico_nome,
                ' . $this->servicoValorSelectExpression() . ' AS servico_valor,
                c.nome AS cliente_nome,
                c.cpf_cnpj AS cliente_documento
            FROM nota_fiscal_servico nfs
            INNER JOIN servicos s ON s.id = nfs.servico_id
            LEFT JOIN clientes c ON c.id = s.cliente_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY nfs.created_at DESC, nfs.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function proximoNumeroRps(): int
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('proximoNumeroRps() exige uma transacao ativa.');
        }

        $stmtLock = $this->pdo->prepare('SELECT id FROM empresa_fiscal_servico ORDER BY id ASC LIMIT 1 FOR UPDATE');
        $stmtLock->execute();

        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(numero_rps), 0) + 1 FROM nota_fiscal_servico');
        $stmt->execute();

        return (int)$stmt->fetchColumn();
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
