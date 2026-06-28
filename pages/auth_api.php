<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($method) {
    case 'GET':
        if ($action === 'check') verificarSessao();
        responder(false, 'Ação inválida.', null, 400);
        break;
    case 'POST':
        if ($action === 'login')  loginUsuario();
        if ($action === 'logout') logoutUsuario();
        responder(false, 'Ação POST inválida.', null, 400);
        break;
    default:
        responder(false, 'Método não suportado.', null, 405);
}

function verificarSessao(): void {
    if (estaLogado()) {
        responder(true, 'Autenticado.', [
            'id'       => $_SESSION['usuario_id'],
            'username' => $_SESSION['usuario_nome'],
            'admin'    => $_SESSION['usuario_admin'] ?? false,
        ]);
    }
    responder(false, 'Não autenticado.', null, 401);
}

function loginUsuario(): void {
    $d    = lerJson();
    $user = trim($d['username'] ?? '');
    $pass = $d['password'] ?? '';
    $pin  = trim($d['pin'] ?? '');

    if (($user === '' && $pin === '') || $pass === '') {
        responder(false, 'Informe (usuário ou PIN) e senha.', null, 422);
    }

    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, pin, is_admin AS admin FROM usuarios
                           WHERE username = :u OR (pin IS NOT NULL AND pin = :p)');
    $stmt->execute([':u' => ($user !== '' ? $user : null),
                    ':p' => ($pin  !== '' ? $pin  : null)]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($pass, $row['password_hash'])) {
        responder(false, 'Credenciais inválidas.', null, 401);
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id']    = (int)$row['id'];
    $_SESSION['usuario_nome']  = $row['username'];
    $_SESSION['usuario_admin'] = (bool)$row['admin'];

    responder(true, 'Autenticado com sucesso.', [
        'username' => $row['username'],
        'admin'    => (bool)$row['admin'],
    ]);
}

function logoutUsuario(): void {
    session_unset();
    session_destroy();
    responder(true, 'Desconectado.');
}
