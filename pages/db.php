<?php
// ─── Configurações do banco ───────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'estoque_manager');
define('DB_USER', 'root');
define('DB_PASS', '');

// ─── Headers comuns ───────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

session_start();

// ─── Conexão PDO (singleton) ──────────────────────────────────
function conectar(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                   DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        responder(false, 'Falha na conexão: ' . $e->getMessage(), null, 500);
    }
    return $pdo;
}

// ─── Resposta JSON padronizada ────────────────────────────────
function responder(bool $ok, string $msg, $data = null, int $status = 200, array $extra = []): void {
    http_response_code($status);
    $payload = array_merge(['ok' => $ok, 'mensagem' => $msg], $extra);
    if ($data !== null) $payload['data'] = $data;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Lê JSON do corpo da requisição ──────────────────────────
function lerJson(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $d = json_decode($raw, true);
    if ($d === null) responder(false, 'JSON inválido.', null, 400);
    return $d;
}

// ─── Verifica sessão ──────────────────────────────────────────
function exigirLogin(): void {
    if (empty($_SESSION['usuario_id'])) {
        responder(false, 'Não autorizado. Faça login.', null, 401);
    }
}

function estaLogado(): bool {
    return !empty($_SESSION['usuario_id']);
}

function isAdmin(): bool {
    return !empty($_SESSION['usuario_admin']);
}
