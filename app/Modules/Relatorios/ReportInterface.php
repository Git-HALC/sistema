<?php

namespace App\Modules\Relatorios;

interface ReportInterface
{
    public function getTitulo(): string;

    public function getNomeArquivo(array $filtros = []): string;

    public function getData(array $filtros): array;

    public function exportarPdf(array $filtros): void;
}
