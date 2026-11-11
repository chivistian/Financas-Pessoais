<?php
class AuthController
{
    /** GET auth/sessao — informa se há usuário logado e entrega o token CSRF. */
    public function sessao(): void
    {
        $usuario = null;
        if (!empty($_SESSION['usuario_id'])) {
            $usuario = (new Usuario())->buscarPorId((int) $_SESSION['usuario_id']);
            if (!$usuario) {
                unset($_SESSION['usuario_id']);
            }
        }
        responder(['ok' => true, 'csrf' => tokenCsrf(), 'usuario' => $usuario]);
    }

    /** POST auth/login — diagrama de sequência "Fazer login". */
    public function login(): void
    {
        $in = entrada();
        $email = mb_strtolower(textoLimpo($in['email'] ?? ''));
        $senha = (string) ($in['senha'] ?? '');

        if ($email === '' || $senha === '') {
            erro('Informe e-mail e senha.', 422);
        }

        $usuario = (new Usuario())->buscarPorEmail($email);

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            erro('E-mail ou senha inválidos.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $usuario['id'];

        responder(['ok' => true, 'redirecionar' => 'painel.html']);
    }

    /** POST auth/cadastro — caso de uso "Cadastrar-se". */
    public function cadastro(): void
    {
        $in = entrada();
        $nome = textoLimpo($in['nome'] ?? '');
        $email = mb_strtolower(textoLimpo($in['email'] ?? ''));
        $senha = (string) ($in['senha'] ?? '');
        $confirmar = (string) ($in['confirmar'] ?? '');

        $erros = [];
        if ($nome === '' || mb_strlen($nome) > 100) {
            $erros['nome'] = 'Informe seu nome (até 100 caracteres).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $erros['email'] = 'Informe um e-mail válido.';
        }
        if (mb_strlen($senha) < 6) {
            $erros['senha'] = 'A senha deve ter pelo menos 6 caracteres.';
        }
        if ($senha !== $confirmar) {
            $erros['confirmar'] = 'As senhas não conferem.';
        }
        if ($erros) {
            erro('Verifique os campos destacados.', 422, ['campos' => $erros]);
        }

        $model = new Usuario();
        if ($model->buscarPorEmail($email)) {
            erro('Este e-mail já está cadastrado.', 409, ['campos' => ['email' => 'E-mail já cadastrado.']]);
        }

        $db = Database::conexao();
        $db->beginTransaction();
        try {
            $id = $model->criar($nome, $email, $senha);
            (new Categoria())->criarPadrao($id);
            (new Conta())->criar($id, 'Carteira', 'carteira', 0);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $id;

        responder(['ok' => true, 'redirecionar' => 'painel.html']);
    }

    /** POST auth/logout */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        responder(['ok' => true, 'redirecionar' => 'index.html']);
    }
}
