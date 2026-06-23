<?php

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'estoque_manager');
define('DB_USER', 'root');
define('DB_PASS', '');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

session_start();

function conectar(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        $err = $e->getMessage();
        $hint = '';
        if (strpos($err, 'Access denied') !== false) {
            $hint = ' Verifique DB_USER/DB_PASS em pages/usuarios_api.php e se o usuário tem permissões no MySQL.';
        }
        responder(false, 'Falha na conexão com o banco de dados: ' . $err . $hint, null, 500);
    }
    return $pdo;
}

function responder(bool $ok, string $mensagem, $data = null, int $status = 200, array $extra = []): void {
    http_response_code($status);
    $payload = array_merge(['ok' => $ok, 'mensagem' => $mensagem], $extra);
    if ($data !== null) $payload['data'] = $data;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        if ($action === 'buscar') buscarUsuario();
        else listarUsuarios();
        break;
    case 'POST':
        $action = $_GET['action'] ?? ($_POST['action'] ?? null);
        if ($action === 'login') loginUsuario();
        if ($action === 'logout') logoutUsuario();
        if ($action === 'register') criarUsuario();
        if ($action === 'atualizarpin') atualizarPin();
        responder(false, 'Ação POST inválida.', null, 400);
        break;
    case 'DELETE':
        excluirUsuario();
        break;
    default:
        responder(false, 'Método não suportado.', null, 405);
}

function estaLogado(): bool {
    return !empty($_SESSION['usuario_id']);
}

function criarUsuario(): void {
    $dados = lerCorpoJson();
    $user  = trim($dados['username'] ?? '');
    $pass  = $dados['password'] ?? '';
    $pin   = trim($dados['pin'] ?? '');
    if ($user === '' || $pass === '') responder(false, 'Usuário e senha são obrigatórios.', null, 422);

    $pdo = conectar();
    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE username = :u');
    $chk->execute([':u' => $user]);
    if ($chk->fetch()) responder(false, 'Nome de usuário já existe.', null, 409);

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO usuarios (username, password_hash, pin) VALUES (:u, :h, :p)');
    $stmt->execute([':u' => $user, ':h' => $hash, ':p' => ($pin !== '' ? $pin : null)]);
    $id = (int)$pdo->lastInsertId();

    responder(true, 'Usuário registrado.', ['id' => $id], 201);
}

function loginUsuario(): void {
    $dados = lerCorpoJson();
    $user  = trim($dados['username'] ?? '');
    $pass  = $dados['password'] ?? '';
    $pin   = trim($dados['pin'] ?? '');
    
    if (($user === '' && $pin === '') || $pass === '') {
        responder(false, 'Informe (usuário ou PIN) e senha.', null, 422);
    }

    $pdo = conectar();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, pin FROM usuarios WHERE username = :u OR pin = :p');
    $stmt->execute([':u' => ($user !== '' ? $user : null), ':p' => ($pin !== '' ? $pin : null)]);
    $row = $stmt->fetch();
    
    if (!$row || !password_verify($pass, $row['password_hash'])) {
        responder(false, 'Credenciais inválidas.', null, 401);
    }

    $_SESSION['usuario_id'] = (int)$row['id'];
    $_SESSION['usuario_nome'] = $row['username'];

    responder(true, 'Autenticado com sucesso.', ['username' => $row['username']]);
}

function logoutUsuario(): void {
    session_unset();
    session_destroy();
    responder(true, 'Desconectado.');
}

function listarUsuarios(): void {
    if (!estaLogado()) responder(false, 'Não autorizado.', null, 401);
    $pdo = conectar();
    $search = trim($_GET['search'] ?? '');
    if ($search === '') {
        $stmt = $pdo->query('SELECT id, username, pin, criado_em FROM usuarios ORDER BY id');
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, username, pin, criado_em FROM usuarios WHERE username LIKE :s ORDER BY id");
        $stmt->execute([':s' => "%{$search}%"]);
        $rows = $stmt->fetchAll();
    }
    responder(true, 'OK', $rows);
}

function buscarUsuario(): void {
    if (!estaLogado()) responder(false, 'Não autorizado.', null, 401);
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $pdo = conectar();
    $stmt = $pdo->prepare('SELECT id, username, criado_em FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) responder(false, 'Usuário não encontrado.', null, 404);
    responder(true, 'OK', $row);
}

function excluirUsuario(): void {
    if (!estaLogado()) responder(false, 'Não autorizado.', null, 401);
    $dados = lerCorpoJson();
    $id = (int)($dados['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $pdo = conectar();
    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) responder(false, 'Usuário não encontrado.', null, 404);
    responder(true, 'Usuário excluído.');
}

function atualizarPin(): void {
    if (!estaLogado()) responder(false, 'Não autorizado.', null, 401);
    $dados = lerCorpoJson();
    $id = (int)($dados['id'] ?? 0);
    $novoPin = trim($dados['pin'] ?? '');
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    
    $pdo = conectar();
    $stmt = $pdo->prepare('UPDATE usuarios SET pin = :p WHERE id = :id');
    $stmt->execute([':p' => ($novoPin !== '' ? $novoPin : null), ':id' => $id]);
    if ($stmt->rowCount() === 0) responder(false, 'Usuário não encontrado.', null, 404);
    responder(true, 'PIN atualizado.');
}

function lerCorpoJson(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $dados = json_decode($raw, true);
    if ($dados === null) responder(false, 'JSON inválido.', null, 400);
    return $dados;
}
