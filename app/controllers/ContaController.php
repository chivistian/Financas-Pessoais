<?php
class ContaController
{
    /** GET conta/listar — inclui o caso de uso "Consultar saldo". */
    public function listar(): void
    {
        $uid = usuarioLogado();
        $contas = (new Conta())->listar($uid);
        $total = array_sum(array_map(fn ($c) => (float) $c['saldo_atual'], $contas));
        responder(['ok' => true, 'contas' => $contas, 'saldo_total' => round($total, 2)]);
    }

    /** POST conta/salvar — cria (sem id) ou edita (com id). */
    public function salvar(): void
    {
        $uid = usuarioLogado();
        $in = entrada();
        $id = (int) ($in['id'] ?? 0);
        $nome = textoLimpo($in['nome'] ?? '');
        $tipo = (string) ($in['tipo'] ?? '');
        $saldoInicial = valorMonetario($in['saldo_inicial'] ?? 0);

        $erros = [];
        if ($nome === '' || mb_strlen($nome) > 60) {
            $erros['nome'] = 'Informe o nome da conta (até 60 caracteres).';
        }
        if (!in_array($tipo, Conta::TIPOS, true)) {
            $erros['tipo'] = 'Selecione o tipo da conta.';
        }
        if ($saldoInicial === null || abs($saldoInicial) > 9999999999.99) {
            $erros['saldo_inicial'] = 'Informe um saldo inicial válido.';
        }
        if ($erros) {
            erro('Verifique os campos destacados.', 422, ['campos' => $erros]);
        }

        $model = new Conta();
        if ($id) {
            if (!$model->buscar($id, $uid)) {
                erro('Conta não encontrada.', 404);
            }
            $model->atualizar($id, $uid, $nome, $tipo, $saldoInicial);
            $msg = 'Conta atualizada com sucesso.';
        } else {
            $id = $model->criar($uid, $nome, $tipo, $saldoInicial);
            $msg = 'Conta criada com sucesso.';
        }

        responder(['ok' => true, 'mensagem' => $msg, 'conta' => $model->buscar($id, $uid)]);
    }

    /** POST conta/excluir */
    public function excluir(): void
    {
        $uid = usuarioLogado();
        $id = (int) (entrada()['id'] ?? 0);
        $model = new Conta();

        if (!$model->buscar($id, $uid)) {
            erro('Conta não encontrada.', 404);
        }
        if ($model->contarLancamentos($id) > 0) {
            erro('Esta conta possui lançamentos. Exclua-os antes de remover a conta.', 409);
        }

        $model->excluir($id, $uid);
        responder(['ok' => true, 'mensagem' => 'Conta excluída.']);
    }
}
