<?php
class LancamentoController
{
    /** GET lancamento/listar?inicio=&fim=&tipo=&conta_id=&categoria_id= */
    public function listar(): void
    {
        $uid = usuarioLogado();
        $f = $this->filtrosGet();
        $model = new Lancamento();
        responder([
            'ok'          => true,
            'lancamentos' => $model->listar($uid, $f),
            'totais'      => $model->totais($uid, $f),
        ]);
    }

    /**
     * POST lancamento/salvar — "Registrar receita", "Registrar despesa" e "Editar lançamento".
     * Segue o diagrama de sequência: verifica sessão, valida dados, cria e atualiza saldo.
     */
    public function salvar(): void
    {
        $uid = usuarioLogado();                       // 4. Verifica sessão do usuário
        $in = entrada();

        $id          = (int) ($in['id'] ?? 0);
        $tipo        = (string) ($in['tipo'] ?? '');
        $valor       = valorMonetario($in['valor'] ?? '');
        $data        = textoLimpo($in['data'] ?? '');
        $contaId     = (int) ($in['conta_id'] ?? 0);
        $categoriaId = (int) ($in['categoria_id'] ?? 0);
        $descricao   = textoLimpo($in['descricao'] ?? '');

        // 5. Valida dados (valor > 0, data, campos obrigatórios)
        $erros = [];
        if (!in_array($tipo, ['R', 'D'], true)) {
            $erros['tipo'] = 'Tipo de lançamento inválido.';
        }
        if ($valor === null || $valor <= 0) {
            $erros['valor'] = 'Informe um valor maior que zero.';
        } elseif ($valor > 9999999999.99) {
            $erros['valor'] = 'Valor muito alto.';
        }
        if (!dataValida($data)) {
            $erros['data'] = 'Informe uma data válida.';
        }
        $conta = $contaId ? (new Conta())->buscar($contaId, $uid) : null;
        if (!$conta) {
            $erros['conta_id'] = 'Selecione uma conta.';
        }
        $categoria = $categoriaId ? (new Categoria())->buscar($categoriaId, $uid) : null;
        if (!$categoria) {
            $erros['categoria_id'] = 'Selecione uma categoria.';
        } elseif ($categoria['tipo'] !== $tipo) {
            $erros['categoria_id'] = 'A categoria não corresponde ao tipo do lançamento.';
        }
        if (mb_strlen($descricao) > 255) {
            $erros['descricao'] = 'A descrição deve ter até 255 caracteres.';
        }

        if ($erros) {                                 // 6. Erro de validação
            erro('Verifique os campos destacados.', 422, ['campos' => $erros]);
        }

        $dados = [
            'tipo' => $tipo, 'valor' => $valor, 'data' => $data,
            'conta_id' => $contaId, 'categoria_id' => $categoriaId, 'descricao' => $descricao,
        ];

        $model = new Lancamento();
        if ($id) {
            $antigo = $model->buscar($id, $uid);
            if (!$antigo) {
                erro('Lançamento não encontrado.', 404);
            }
            $model->atualizar($id, $antigo, $dados);
            $msg = 'Lançamento atualizado com sucesso.';
        } else {
            $id = $model->criar($dados);              // 8 a 13. criar + atualizar saldo
            $msg = $tipo === 'D' ? 'Despesa registrada com sucesso.' : 'Receita registrada com sucesso.';
        }

        // 14. Sucesso (JSON) com o saldo atualizado
        responder([
            'ok'       => true,
            'mensagem' => $msg,
            'id'       => $id,
            'conta'    => (new Conta())->buscar($contaId, $uid),
        ]);
    }

    /** POST lancamento/excluir */
    public function excluir(): void
    {
        $uid = usuarioLogado();
        $id = (int) (entrada()['id'] ?? 0);
        $model = new Lancamento();
        $antigo = $model->buscar($id, $uid);

        if (!$antigo) {
            erro('Lançamento não encontrado.', 404);
        }

        $model->excluir($antigo);
        responder([
            'ok'       => true,
            'mensagem' => 'Lançamento excluído e saldo estornado.',
            'conta'    => (new Conta())->buscar((int) $antigo['conta_id'], $uid),
        ]);
    }

    private function filtrosGet(): array
    {
        $f = [];
        foreach (['inicio', 'fim'] as $campo) {
            $v = (string) ($_GET[$campo] ?? '');
            if ($v !== '') {
                if (!dataValida($v)) {
                    erro('Data de filtro inválida.', 422);
                }
                $f[$campo] = $v;
            }
        }
        $tipo = (string) ($_GET['tipo'] ?? '');
        if (in_array($tipo, ['R', 'D'], true)) {
            $f['tipo'] = $tipo;
        }
        foreach (['conta_id', 'categoria_id'] as $campo) {
            if (!empty($_GET[$campo])) {
                $f[$campo] = (int) $_GET[$campo];
            }
        }
        return $f;
    }
}
