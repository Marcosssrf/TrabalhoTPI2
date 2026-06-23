<?php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'estoque_manager');
define('DB_USER', 'root');
define('DB_PASS', '');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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
        responder(false, 'Falha na conexão com o banco de dados: ' . $e->getMessage(), null, 500);
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
        if ($action === 'buscar') {
            buscarCliente();
        } else {
            listarClientes();
        }
        break;

    case 'POST':
        criarCliente();
        break;

    case 'PUT':
        atualizarCliente();
        break;

    case 'DELETE':
        excluirCliente();
        break;

    default:
        responder(false, 'Método não suportado.', null, 405);
}


function listarClientes(): void {
    $pdo      = conectar();
    $search   = trim($_GET['search']   ?? '');
    $pagina   = max(1, (int)($_GET['pagina']    ?? 1));
    $porPagina= max(1, min(100, (int)($_GET['por_pagina'] ?? 10)));
    $offset   = ($pagina - 1) * $porPagina;

$where  = '';
$params = [];
if ($search !== '') {
        $where    = "WHERE nome_completo LIKE :s OR documento LIKE :s OR email LIKE :s";
        $params[':s'] = "%{$search}%";
    }

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM clientes {$where}");
    $stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$sql = "SELECT id, tipo_pessoa, nome_completo, documento, categoria, email, telefone
            FROM clientes {$where}
            ORDER BY nome_completo
            LIMIT :lim OFFSET :off";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,    PDO::PARAM_INT);
    $stmt->execute();

    $clientes = $stmt->fetchAll();

    responder(true, 'OK', $clientes, 200, [
        'total'      => $total,
        'pagina'     => $pagina,
        'por_pagina' => $porPagina,
    ]);
}


function buscarCliente(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $cliente = $stmt->fetch();

    if (!$cliente) responder(false, 'Cliente não encontrado.', null, 404);

    responder(true, 'OK', $cliente);
}

function criarCliente(): void {
    $dados = lerCorpoJson();
    validarDados($dados);

$pdo = conectar();
$chk = $pdo->prepare('SELECT id FROM clientes WHERE documento = :doc');
    $chk->execute([':doc' => $dados['documento']]);
    if ($chk->fetch()) responder(false, 'Documento já cadastrado.', null, 409);

    $sql = "INSERT INTO clientes
              (tipo_pessoa, nome_completo, documento, data_nasc, limite_credito,
               categoria, telefone, celular, email,
               cep, logradouro, numero, complemento, bairro, cidade, estado)
                        VALUES
                            (:tipo_pessoa, :nome_completo, :documento, :data_nasc, :limite_credito,
                             :categoria, :telefone, :celular, :email,
                             :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(mapearParams($dados));

        $novoId = (int)$pdo->lastInsertId();
    responder(true, 'Cliente cadastrado com sucesso.', ['id' => $novoId], 201);
}


function atualizarCliente(): void {
    $dados = lerCorpoJson();
    $id    = (int)($dados['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    validarDados($dados);

$pdo = conectar();
$chk = $pdo->prepare('SELECT id FROM clientes WHERE id = :id');
    $chk->execute([':id' => $id]);
if (!$chk->fetch()) responder(false, 'Cliente não encontrado.', null, 404);
$chkDoc = $pdo->prepare('SELECT id FROM clientes WHERE documento = :doc AND id <> :id');
    $chkDoc->execute([':doc' => $dados['documento'], ':id' => $id]);
    if ($chkDoc->fetch()) responder(false, 'Documento já cadastrado para outro cliente.', null, 409);

    $sql = "UPDATE clientes SET
              tipo_pessoa    = :tipo_pessoa,
              nome_completo  = :nome_completo,
              documento      = :documento,
              data_nasc      = :data_nasc,
              limite_credito = :limite_credito,
              categoria      = :categoria,
              telefone       = :telefone,
              celular        = :celular,
              email          = :email,
              cep            = :cep,
              logradouro     = :logradouro,
              numero         = :numero,
              complemento    = :complemento,
              bairro         = :bairro,
              cidade         = :cidade,
              estado         = :estado,
              atualizado_em  = NOW()
            WHERE id = :id";

$params       = mapearParams($dados);
$params[':id'] = $id;

    $pdo->prepare($sql)->execute($params);
responder(true, 'Cliente atualizado com sucesso.');
}
function excluirCliente(): void {
    $dados = lerCorpoJson();
    $id    = (int)($dados['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    $pdo  = conectar();
    $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) responder(false, 'Cliente não encontrado.', null, 404);

    responder(true, 'Cliente excluído com sucesso.');
}
function lerCorpoJson(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) responder(false, 'Corpo da requisição vazio.', null, 400);

    $dados = json_decode($raw, true);
    if ($dados === null) responder(false, 'JSON inválido.', null, 400);

    return $dados;
}

function validarDados(array $d): void {
    $obrig = ['tipo_pessoa', 'nome_completo', 'documento', 'telefone', 'email',
              'cep', 'logradouro', 'cidade'];

    $faltando = array_filter($obrig, fn($k) => empty($d[$k]));
    if ($faltando) {
        responder(false, 'Campos obrigatórios ausentes: ' . implode(', ', $faltando), null, 422);
    }

    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        responder(false, 'E-mail inválido.', null, 422);
    }

    if (!in_array($d['tipo_pessoa'], ['PF', 'PJ'], true)) {
        responder(false, 'Tipo de pessoa inválido (use PF ou PJ).', null, 422);
    }
}

function mapearParams(array $d): array {
    return [
        ':tipo_pessoa'    => $d['tipo_pessoa'],
        ':nome_completo'  => $d['nome_completo'],
        ':documento'      => $d['documento'],
        ':data_nasc'      => !empty($d['data_nasc'])      ? $d['data_nasc']      : null,
        ':limite_credito' => isset($d['limite_credito']) && $d['limite_credito'] !== ''
                             ? (float)$d['limite_credito'] : null,
        ':categoria'      => !empty($d['categoria'])      ? $d['categoria']      : null,
        ':telefone'       => $d['telefone'],
        ':celular'        => !empty($d['celular'])         ? $d['celular']        : null,
        ':email'          => $d['email'],
        ':cep'            => $d['cep'],
        ':logradouro'     => $d['logradouro'],
        ':numero'         => !empty($d['numero'])          ? $d['numero']         : null,
        ':complemento'    => !empty($d['complemento'])     ? $d['complemento']    : null,
        ':bairro'         => !empty($d['bairro'])          ? $d['bairro']         : null,
        ':cidade'         => $d['cidade'],
        ':estado'         => !empty($d['estado'])          ? $d['estado']         : null,
    ];
}
