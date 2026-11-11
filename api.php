<?php
/**
 * Front controller da API. Uso: api.php?r=controle/acao
 * Ex.: POST api.php?r=lancamento/salvar
 */
ini_set('display_errors', '0');
require __DIR__ . '/app/bootstrap.php';

iniciarSessao();

$rotas = [
    'GET auth/sessao'          => [AuthController::class, 'sessao'],
    'POST auth/login'          => [AuthController::class, 'login'],
    'POST auth/cadastro'       => [AuthController::class, 'cadastro'],
    'POST auth/logout'         => [AuthController::class, 'logout'],

    'GET dashboard/resumo'     => [DashboardController::class, 'resumo'],

    'GET conta/listar'         => [ContaController::class, 'listar'],
    'POST conta/salvar'        => [ContaController::class, 'salvar'],
    'POST conta/excluir'       => [ContaController::class, 'excluir'],

    'GET categoria/listar'     => [CategoriaController::class, 'listar'],
    'POST categoria/salvar'    => [CategoriaController::class, 'salvar'],
    'POST categoria/excluir'   => [CategoriaController::class, 'excluir'],

    'GET lancamento/listar'    => [LancamentoController::class, 'listar'],
    'POST lancamento/salvar'   => [LancamentoController::class, 'salvar'],
    'POST lancamento/excluir'  => [LancamentoController::class, 'excluir'],

    'GET relatorio/mensal'     => [RelatorioController::class, 'mensal'],
];

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rota = trim((string) ($_GET['r'] ?? ''), '/');
    $chave = "$metodo $rota";

    if (!isset($rotas[$chave])) {
        erro('Rota não encontrada.', 404);
    }
    if ($metodo === 'POST') {
        verificarCsrf();
    }

    [$classe, $acao] = $rotas[$chave];
    (new $classe())->$acao();
} catch (PDOException $e) {
    error_log('[financas] ' . $e->getMessage());
    erro('Erro ao acessar o banco de dados. Verifique se o MySQL está ativo e se o install.php foi executado.', 500);
} catch (Throwable $e) {
    error_log('[financas] ' . $e->getMessage());
    erro('Erro interno do servidor.', 500);
}
