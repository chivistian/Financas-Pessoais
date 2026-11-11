<?php
/**
 * Instalador: cria o banco, as tabelas e um usuário de demonstração com dados de exemplo.
 * Acesse: http://localhost/financas-pessoais/install.php
 * Para recriar os dados de demonstração: install.php?reset=1
 * IMPORTANTE: remova este arquivo em um ambiente de produção.
 */
require __DIR__ . '/app/bootstrap.php';

const DEMO_EMAIL = 'demo@financas.com';
const DEMO_SENHA = 'demo123';
const DEMO_NOME  = 'Usuário Demonstração';

$log = [];
$sucesso = true;

try {
    // 1. Cria o banco (conexão sem selecionar database)
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');
    $log[] = 'Banco de dados "' . DB_NAME . '" pronto.';

    // 2. Cria as tabelas a partir do schema.sql (ignorando CREATE DATABASE / USE)
    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $comando) {
        if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $comando)) {
            continue;
        }
        $pdo->exec($comando);
    }
    $log[] = 'Tabelas usuario, conta, categoria e lancamento criadas.';

    // 3. Usuário de demonstração
    $db = Database::conexao();
    $usuarioModel = new Usuario();
    $existente = $usuarioModel->buscarPorEmail(DEMO_EMAIL);

    if ($existente && isset($_GET['reset'])) {
        $st = $db->prepare('DELETE l FROM lancamento l JOIN conta c ON c.id = l.conta_id WHERE c.usuario_id = ?');
        $st->execute([$existente['id']]);
        $db->prepare('DELETE FROM usuario WHERE id = ?')->execute([$existente['id']]);
        $existente = null;
        $log[] = 'Dados de demonstração anteriores removidos.';
    }

    if ($existente) {
        $log[] = 'Usuário de demonstração já existia (use ?reset=1 para recriar os dados).';
    } else {
        $uid = $usuarioModel->criar(DEMO_NOME, DEMO_EMAIL, DEMO_SENHA);

        $catModel = new Categoria();
        $catModel->criarPadrao($uid);
        $cat = [];
        foreach ($catModel->listar($uid) as $c) {
            $cat[$c['nome']] = (int) $c['id'];
        }

        $contaModel = new Conta();
        $carteira  = $contaModel->criar($uid, 'Carteira', 'carteira', 800);
        $corrente  = $contaModel->criar($uid, 'Banco - Conta Corrente', 'corrente', 3500);
        $poupanca  = $contaModel->criar($uid, 'Banco - Poupança', 'poupanca', 10000);

        // [dia, tipo, categoria, conta, valor, descrição]
        $modelo = [
            [5,  'R', 'Salário',     $corrente, 5200.00, 'Salário mensal'],
            [6,  'D', 'Moradia',     $corrente, 1500.00, 'Aluguel'],
            [7,  'D', 'Moradia',     $corrente, 189.90,  'Conta de luz'],
            [8,  'D', 'Alimentação', $corrente, 620.45,  'Supermercado'],
            [10, 'D', 'Transporte',  $carteira, 250.00,  'Combustível'],
            [12, 'D', 'Lazer',       $carteira, 80.00,   'Cinema'],
            [15, 'D', 'Saúde',       $carteira, 95.90,   'Farmácia'],
            [18, 'R', 'Freelance',   $corrente, 1200.00, 'Desenvolvimento de site'],
            [20, 'D', 'Educação',    $corrente, 199.90,  'Curso online'],
            [22, 'D', 'Alimentação', $carteira, 135.50,  'Restaurante'],
            [25, 'R', 'Rendimentos', $poupanca, 52.30,   'Rendimento da poupança'],
            [27, 'D', 'Lazer',       $corrente, 49.90,   'Streaming'],
        ];

        $lancModel = new Lancamento();
        $hoje = new DateTime('today');
        $diaHoje = (int) $hoje->format('j');
        $total = 0;

        // Três meses anteriores completos + mês atual (proporcional até hoje)
        for ($offset = 3; $offset >= 0; $offset--) {
            $mes = (new DateTime('first day of this month'))->modify("-$offset months");
            foreach ($modelo as $i => [$dia, $tipo, $categoria, $conta, $valor, $desc]) {
                if ($offset === 2 && $categoria === 'Freelance') {
                    continue; // variação entre os meses
                }
                if ($offset === 0) {
                    $dia = max(1, (int) ceil($dia * min($diaHoje, 28) / 28));
                }
                $variacao = $tipo === 'D' && !in_array($categoria, ['Moradia', 'Educação'], true)
                    ? 1 + (($offset * 7 + $i) % 5 - 2) / 20   // ±10%
                    : 1;
                $lancModel->criar([
                    'tipo'         => $tipo,
                    'valor'        => round($valor * $variacao, 2),
                    'data'         => $mes->format('Y-m-') . str_pad((string) $dia, 2, '0', STR_PAD_LEFT),
                    'conta_id'     => $conta,
                    'categoria_id' => $cat[$categoria],
                    'descricao'    => $desc,
                ]);
                $total++;
            }
        }
        $log[] = "Usuário de demonstração criado com 3 contas e $total lançamentos.";
    }
} catch (Throwable $e) {
    $sucesso = false;
    $log[] = 'ERRO: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Finanças Pessoais</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pagina-auth">
<main class="auth-card" style="max-width:560px">
    <h1><?= $sucesso ? 'Instalação concluída' : 'Falha na instalação' ?></h1>
    <ul class="install-log">
        <?php foreach ($log as $linha): ?>
            <li><?= htmlspecialchars($linha) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($sucesso): ?>
        <div class="demo-box">
            <strong>Acesso de demonstração</strong><br>
            E-mail: <code><?= DEMO_EMAIL ?></code><br>
            Senha: <code><?= DEMO_SENHA ?></code>
        </div>
        <a class="btn btn-primario btn-bloco" href="index.html">Ir para o login</a>
    <?php else: ?>
        <p>Verifique se o MySQL está em execução e se as credenciais em <code>config/config.php</code> estão corretas.</p>
    <?php endif; ?>
</main>
</body>
</html>
