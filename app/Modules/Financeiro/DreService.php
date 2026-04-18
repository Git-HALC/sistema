<?php

namespace App\Modules\Financeiro;

class DreService
{
    public function __construct(private readonly DreRepository $repo) {}

    public function gerar(int $mes, int $ano): array
    {
        $mes = max(1, min(12, $mes));
        $ano = $ano > 0 ? $ano : (int)date('Y');

        $dataInicio = sprintf('%04d-%02d-01', $ano, $mes);
        $dataFim    = date('Y-m-t', strtotime($dataInicio));

        return $this->repo->gerar($dataInicio, $dataFim);
    }
}
