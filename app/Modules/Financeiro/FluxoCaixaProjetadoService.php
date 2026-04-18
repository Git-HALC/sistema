<?php
declare(strict_types=1);

namespace App\Modules\Financeiro;

final class FluxoCaixaProjetadoService
{
    public function __construct(private readonly FluxoCaixaProjetadoRepository $repo) {}

    /**
     * Monta o fluxo projetado para um agrupamento (diario/semanal/mensal).
     *
     * @param string $agrupamento  'diario' | 'semanal' | 'mensal'
     * @return array{
     *   saldo_atual: float,
     *   agrupamento: string,
     *   periodos: array<int, array{label:string, data_ini:string, data_fim:string, entradas:float, saidas:float, saldo_projetado:float, entradas_det:array, saidas_det:array}>
     * }
     */
    public function projetar(string $agrupamento): array
    {
        $saldo = $this->repo->saldoAtualContasAtivas();
        $periodos = $this->buildPeriodos($agrupamento);

        if ($periodos === []) {
            return ['saldo_atual' => $saldo, 'agrupamento' => $agrupamento, 'periodos' => []];
        }

        $inicioGlobal = $periodos[0]['data_ini'];
        $fimGlobal    = $periodos[count($periodos) - 1]['data_fim'];

        $entradas = $this->repo->entradasPrevistas($inicioGlobal, $fimGlobal);
        $saidas   = $this->repo->saidasPrevistas($inicioGlobal, $fimGlobal);

        $saldoAcum = $saldo;
        foreach ($periodos as &$p) {
            $p['entradas'] = 0.0;
            $p['saidas'] = 0.0;
            $p['entradas_det'] = [];
            $p['saidas_det'] = [];

            foreach ($entradas as $e) {
                if ($e['data'] >= $p['data_ini'] && $e['data'] <= $p['data_fim']) {
                    $p['entradas'] += (float)$e['valor'];
                    $p['entradas_det'][] = $e;
                }
            }
            foreach ($saidas as $s) {
                if ($s['data'] >= $p['data_ini'] && $s['data'] <= $p['data_fim']) {
                    $p['saidas'] += (float)$s['valor'];
                    $p['saidas_det'][] = $s;
                }
            }

            $saldoAcum += $p['entradas'] - $p['saidas'];
            $p['saldo_projetado'] = round($saldoAcum, 2);
        }
        unset($p);

        return [
            'saldo_atual' => round($saldo, 2),
            'agrupamento' => $agrupamento,
            'periodos' => $periodos,
        ];
    }

    /**
     * @return array<int, array{label:string, data_ini:string, data_fim:string}>
     */
    private function buildPeriodos(string $agrupamento): array
    {
        $hoje = new \DateTimeImmutable('today');
        $out = [];

        return match ($agrupamento) {
            'semanal' => $this->gerarSemanas($hoje),
            'mensal'  => $this->gerarMeses($hoje),
            default   => $this->gerarDias($hoje, 7),
        };
    }

    private function gerarDias(\DateTimeImmutable $ref, int $qtd): array
    {
        $out = [];
        for ($i = 0; $i < $qtd; $i++) {
            $d = $ref->modify('+' . $i . ' day');
            $iso = $d->format('Y-m-d');
            $out[] = [
                'label' => $d->format('d/m') . ' (' . $this->diaSemanaPt($d) . ')',
                'data_ini' => $iso,
                'data_fim' => $iso,
            ];
        }
        return $out;
    }

    private function gerarSemanas(\DateTimeImmutable $ref): array
    {
        $out = [];
        for ($i = 0; $i < 4; $i++) {
            $ini = $ref->modify('+' . ($i * 7) . ' day');
            $fim = $ini->modify('+6 day');
            $out[] = [
                'label' => 'Semana ' . ($i + 1) . ' (' . $ini->format('d/m') . ' - ' . $fim->format('d/m') . ')',
                'data_ini' => $ini->format('Y-m-d'),
                'data_fim' => $fim->format('Y-m-d'),
            ];
        }
        return $out;
    }

    private function gerarMeses(\DateTimeImmutable $ref): array
    {
        $out = [];
        $cursor = $ref;
        for ($i = 0; $i < 3; $i++) {
            $ini = $cursor->modify('first day of this month');
            $fim = $cursor->modify('last day of this month');
            // Para o mês atual, começar em "hoje" em vez do dia 1
            if ($i === 0 && $ref > $ini) {
                $ini = $ref;
            }
            $out[] = [
                'label' => ucfirst($this->mesPt($cursor)) . '/' . $cursor->format('Y'),
                'data_ini' => $ini->format('Y-m-d'),
                'data_fim' => $fim->format('Y-m-d'),
            ];
            $cursor = $cursor->modify('first day of next month');
        }
        return $out;
    }

    private function diaSemanaPt(\DateTimeImmutable $d): string
    {
        return ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int)$d->format('w')];
    }

    private function mesPt(\DateTimeImmutable $d): string
    {
        return [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][(int)$d->format('n')];
    }
}
