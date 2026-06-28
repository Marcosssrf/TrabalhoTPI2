<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        exigirLogin();
        if ($action === 'buscar') buscarUsuario();
        listarUsuarios();
        break;
    case 'POST':
        // Cadastro é público; demais ações exigem login
        if ($action === 'register') { criarUsuario(); break; }
        exigirLogin();
        if ($action === 'atualizarpin') atualizarPin();
        if ($action === 'trocarsenha')  trocarSenha();
        responder(false, 'Ação inválida.', null, 400);
        break;
    case 'PUT':
        exigirLogin();
        atualizarUsuario();
        break;
    case 'DELETE':
        exigirLogin();
        if (!isAdmin()) responder(false, 'Apenas administradores podem excluir usuários.', null, 403);
        excluirUsuario();
        break;
    default:
        responder(false, 'Método não suportado.', null, 405);
}

// ── CRUD ──────────────────────────────────────────────────────

function listarUsuarios(): void {
    $pdo    = conectar();
    $search = trim($_GET['search'] ?? '');
    if ($search === '') {
        $stmt = $pdo->query('SELECT id, username, pin, is_admin AS admin, criado_em FROM usuarios ORDER BY id');
    } else {
        $stmt = $pdo->prepare("SELECT id, username, pin, is_admin AS admin, criado_em FROM usuarios
                                WHERE username LIKE :s ORDER BY id");
        $stmt->execute([':s' => "%{$search}%"]);
    }
    responder(true, 'OK', $stmt->fetchAll());
}

function buscarUsuario(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT id, username, pin, is_admin AS admin, criado_em FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) responder(false, 'Usuário não encontrado.', null, 404);
    responder(true, 'OK', $row);
}

function criarUsuario(): void {
    $d    = lerJson();
    $user = trim($d['username'] ?? '');
    $pass = $d['password'] ?? '';
    $pin  = trim($d['pin'] ?? '');

    if ($user === '' || $pass === '') responder(false, 'Usuário e senha são obrigatórios.', null, 422);
    if (strlen($pass) < 4) responder(false, 'Senha deve ter ao menos 4 caracteres.', null, 422);

    $pdo = conectar();
    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE username = :u');
    $chk->execute([':u' => $user]);
    if ($chk->fetch()) responder(false, 'Nome de usuário já existe.', null, 409);

    $hash  = password_hash($pass, PASSWORD_DEFAULT);
    $admin = isset($d['admin']) && $d['admin'] ? 1 : 0;
    $stmt  = $pdo->prepare('INSERT INTO usuarios (username, password_hash, pin, is_admin)
                             VALUES (:u, :h, :p, :a)');
    $stmt->execute([':u' => $user, ':h' => $hash,
                    ':p' => ($pin !== '' ? $pin : null), ':a' => $admin]);
    responder(true, 'Usuário cadastrado.', ['id' => (int)$pdo->lastInsertId()], 201);
}

function atualizarUsuario(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    // Só admin pode alterar outros; usuário comum pode alterar a si mesmo
    if (!isAdmin() && $id !== $_SESSION['usuario_id']) {
        responder(false, 'Sem permissão.', null, 403);
    }

    $user  = trim($d['username'] ?? '');
    $pin   = trim($d['pin'] ?? '');
    $admin = isset($d['admin']) && $d['admin'] ? 1 : 0;
    if ($user === '') responder(false, 'Username obrigatório.', null, 422);

    $pdo = conectar();
    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE username = :u AND id <> :id');
    $chk->execute([':u' => $user, ':id' => $id]);
    if ($chk->fetch()) responder(false, 'Nome de usuário já existe.', null, 409);

    $pdo->prepare('UPDATE usuarios SET username = :u, pin = :p, is_admin = :a WHERE id = :id')
        ->execute([':u' => $user, ':p' => ($pin !== '' ? $pin : null),
                   ':a' => $admin, ':id' => $id]);
    responder(true, 'Usuário atualizado.');
}

function excluirUsuario(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    if ($id === $_SESSION['usuario_id']) responder(false, 'Não pode excluir a si mesmo.', null, 400);

    $pdo  = conectar();
    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) responder(false, 'Usuário não encontrado.', null, 404);
    responder(true, 'Usuário excluído.');
}

function atualizarPin(): void {
    $d      = lerJson();
    $id     = (int)($d['id'] ?? 0);
    $novoPin = trim($d['pin'] ?? '');
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    if (!isAdmin() && $id !== $_SESSION['usuario_id']) {
        responder(false, 'Sem permissão.', null, 403);
    }
    $pdo = conectar();
    $pdo->prepare('UPDATE usuarios SET pin = :p WHERE id = :id')
        ->execute([':p' => ($novoPin !== '' ? $novoPin : null), ':id' => $id]);
    responder(true, 'PIN atualizado.');
}

function trocarSenha(): void {
    $d      = lerJson();
    $id     = (int)($d['id'] ?? 0);
    $nova   = $d['nova_senha'] ?? '';
    if ($id <= 0 || strlen($nova) < 4) responder(false, 'Dados inválidos.', null, 422);
    if (!isAdmin() && $id !== $_SESSION['usuario_id']) {
        responder(false, 'Sem permissão.', null, 403);
    }
    $hash = password_hash($nova, PASSWORD_DEFAULT);
    conectar()->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id')
              ->execute([':h' => $hash, ':id' => $id]);
    responder(true, 'Senha atualizada.');
}
