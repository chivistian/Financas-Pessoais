<?php
class CategoriaController
{
    /** GET categoria/listar */
    public function listar(): void
    {
        $uid = usuarioLogado();
        responder(['ok' => true, 'categorias' => (new Categoria())->listar($uid)]);
    }

    /** POST categoria/salvar */
    public function salvar(): void
    {
        $uid = usuarioLogado();
        $in = entrada();
        $id = (int) ($in['id'] ?? 0);
        $nome = textoLimpo($in['nome'] ?? '');
        $tipo = (string) ($in['tipo'] ?? '');

        $erros = [];
        if ($nome === '' || mb_strlen($nome) > 60) {
            $erros['nome'] = 'Informe o nome da categoria (até 60 caracteres).';
        }
        if (!in_array($tipo, ['R', 'D'], true)) {
            $erros['tipo'] = 'Selecione se é de receita ou despesa.';
        }
        if ($erros) {
            erro('Verifique os campos destacados.', 422, ['campos' => $erros]);
        }

        $model = new Categoria();
        if ($model->existeNome($uid, $nome, $tipo, $id)) {
            erro('Já existe uma categoria com esse nome.', 409, ['campos' => ['nome' => 'Nome já utilizado.']]);
        }

        if ($id) {
            $atual = $model->buscar($id, $uid);
            if (!$atual) {
                erro('Categoria não encontrada.', 404);
            }
            if ($atual['tipo'] !== $tipo && $model->contarLancamentos($id) > 0) {
                erro('Não é possível mudar o tipo de uma categoria que já possui lançamentos.', 409);
            }
            $model->atualizar($id, $uid, $nome, $tipo);
            $msg = 'Categoria atualizada com sucesso.';
        } else {
            $id = $model->criar($uid, $nome, $tipo);
            $msg = 'Categoria criada com sucesso.';
        }

        responder(['ok' => true, 'mensagem' => $msg, 'categoria' => $model->buscar($id, $uid)]);
    }

    /** POST categoria/excluir */
    public function excluir(): void
    {
        $uid = usuarioLogado();
        $id = (int) (entrada()['id'] ?? 0);
        $model = new Categoria();

        if (!$model->buscar($id, $uid)) {
            erro('Categoria não encontrada.', 404);
        }
        if ($model->contarLancamentos($id) > 0) {
            erro('Esta categoria possui lançamentos e não pode ser excluída.', 409);
        }

        $model->excluir($id, $uid);
        responder(['ok' => true, 'mensagem' => 'Categoria excluída.']);
    }
}
