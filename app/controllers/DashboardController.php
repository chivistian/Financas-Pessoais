<?php
class DashboardController
{
    /** GET dashboard/resumo — painel com saldo e resumo do mês. */
    public function resumo(): void
    {
        $uid = usuarioLogado();
        $inicioMes = date('Y-m-01');
        $fimMes = date('Y-m-t');

        $contas = (new Conta())->listar($uid);
        $saldoTotal = array_sum(array_map(fn ($c) => (float) $c['saldo_atual'], $contas));

        $lanc = new Lancamento();
        $periodo = ['inicio' => $inicioMes, 'fim' => $fimMes];

        $despesasCategoria = array_values(array_filter(
            $lanc->totaisPorCategoria($uid, $periodo),
            fn ($r) => $r['tipo'] === 'D'
        ));

        // Evolução dos últimos 6 meses (preenche meses sem movimento com zero)
        $inicioEvolucao = (new DateTime($inicioMes))->modify('-5 months');
        $meses = [];
        $cursor = clone $inicioEvolucao;
        for ($i = 0; $i < 6; $i++) {
            $k = $cursor->format('Y-m');
            $meses[$k] = ['mes' => $k, 'receitas' => 0.0, 'despesas' => 0.0];
            $cursor->modify('+1 month');
        }
        foreach ($lanc->evolucaoMensal($uid, $inicioEvolucao->format('Y-m-d'), $fimMes) as $e) {
            if (isset($meses[$e['mes']])) {
                $meses[$e['mes']]['receitas'] = (float) $e['receitas'];
                $meses[$e['mes']]['despesas'] = (float) $e['despesas'];
            }
        }

        responder([
            'ok'                 => true,
            'mes'                => date('Y-m'),
            'saldo_total'        => round($saldoTotal, 2),
            'contas'             => $contas,
            'totais_mes'         => $lanc->totais($uid, $periodo),
            'despesas_categoria' => $despesasCategoria,
            'evolucao'           => array_values($meses),
            'ultimos'            => $lanc->listar($uid, ['limite' => 6]),
        ]);
    }
}
