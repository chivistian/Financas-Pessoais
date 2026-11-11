<?php
/**
 * Funções utilitárias: sessão, respostas JSON, CSRF e validação.
 */

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_name('FINANCASSESSID');
        session_start();
    }
}

/** Envia uma resposta JSON e encerra a requisição. */
function responder(array $dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function erro(string $mensagem, int $status = 400, array $extras = []): void
{
    responder(array_merge(['ok' => false, 'erro' => $mensagem], $extras), $status);
}

/** Lê o corpo da requisição (JSON ou formulário). */
function entrada(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($tipo, 'application/json') !== false) {
        $dados = json_decode(file_get_contents('php://input'), true);
        return is_array($dados) ? $dados : [];
    }
    return $_POST;
}

/** Garante que existe um usuário logado e retorna seu id. */
function usuarioLogado(): int
{
    if (empty($_SESSION['usuario_id'])) {
        erro('Sessão expirada. Faça login novamente.', 401);
    }
    return (int) $_SESSION['usuario_id'];
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verificarCsrf(): void
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($enviado) || !hash_equals(tokenCsrf(), $enviado)) {
        erro('Token de segurança inválido. Recarregue a página.', 419);
    }
}

/** Valida data no formato AAAA-MM-DD. */
function dataValida($data): bool
{
    if (!is_string($data) || $data === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('!Y-m-d', $data);
    return $dt !== false && $dt->format('Y-m-d') === $data;
}

/** Converte "1234.56", "1.234,56" ou número em float com 2 casas. Retorna null se inválido. */
function valorMonetario($valor): ?float
{
    if (is_int($valor) || is_float($valor)) {
        return round((float) $valor, 2);
    }
    $s = trim((string) $valor);
    if ($s === '') {
        return null;
    }
    if (strpos($s, ',') !== false) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    }
    return is_numeric($s) ? round((float) $s, 2) : null;
}

/** Formata float para gravação em DECIMAL(12,2). */
function dinheiro(float $valor): string
{
    return number_format($valor, 2, '.', '');
}

function textoLimpo($valor): string
{
    return trim((string) ($valor ?? ''));
}
