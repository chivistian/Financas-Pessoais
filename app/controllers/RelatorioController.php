<?php
class RelatorioController
{
    /**
     * GET relatorio/mensal?mes=AAAA-MM[&categoria_id=]
     * ou  relatorio/mensal?inicio=AAAA-MM-DD&fim=AAAA-MM-DD[&categoria_id=]
     * "Gerar relatório mensal por categoria" estendido por "Filtrar por período e categoria".
     */
    public function mensal(): void
    {
        $uid = usuarioLogado();
        $inicio = (string) ($_GET['inicio'] ?? '');
        $fim = (string) ($_GET['fim'] ?? '');
        $mes = (string) ($_GET['mes'] ?? '');

        if ($inicio !== '' || $fim !== '') {
            if (!dataValida($inicio) || !dataValida($fim)) {
                erro('Informe datas de início e fim válidas.', 422);
            }
            if ($inicio > $fim) {
                erro('A data inicial deve ser anterior ou igual à data final.', 422);
            }
            $mes = null;
        } else {
            if ($mes === '') {
                $mes = date('Y-m');
            }
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
                erro('Mês inválido.', 422);
            }
            $inicio = "$mes-01";
            $fim = date('Y-m-t', strtotime($inicio));
        }

        $categoria = null;
        $categoriaId = (int) ($_GET['categoria_id'] ?? 0);
        if ($categoriaId) {
            $categoria = (new Categoria())->buscar($categoriaId, $uid);
            if (!$categoria) {
                erro('Categoria não encontrada.', 404);
            }
        }

        $f = ['inicio' => $inicio, 'fim' => $fim, 'categoria_id' => $categoriaId ?: null];
        $model = new Lancamento();

        responder([
            'ok'            => true,
            'mes'           => $mes,
            'inicio'        => $inicio,
            'fim'           => $fim,
            'categoria'     => $categoria,
            'totais'        => $model->totais($uid, $f),
            'por_categoria' => $model->totaisPorCategoria($uid, $f),
            'lancamentos'   => $model->listar($uid, $f),
        ]);
    }
}
