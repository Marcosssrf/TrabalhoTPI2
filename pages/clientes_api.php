<?php
require_once 'db.php';
exigirLogin();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        if ($action === 'buscar') buscarCliente();
        listarClientes();
        break;
    case 'POST':   criarCliente();     break;
    case 'PUT':    atualizarCliente(); break;
    case 'DELETE': excluirCliente();   break;
    default: responder(false, 'Método não suportado.', null, 405);
}

function listarClientes(): void {
    $pdo    = conectar();
    $search = trim($_GET['search']   ?? '');
    $pagina = max(1, (int)($_GET['pagina']    ?? 1));
    $porPag = max(1, min(100, (int)($_GET['por_pagina'] ?? 10)));
    $offset = ($pagina - 1) * $porPag;

    $where = ''; $params = [];
    if ($search !== '') {
        $where = "WHERE nome_completo LIKE :s OR documento LIKE :s OR email LIKE :s";
        $params[':s'] = "%{$search}%";
    }

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM clientes {$where}");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $sql  = "SELECT id, tipo_pessoa, nome_completo, documento, categoria, email, telefone
             FROM clientes {$where} ORDER BY nome_completo LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $porPag, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    responder(true, 'OK', $stmt->fetchAll(), 200, [
        'total' => $total, 'pagina' => $pagina, 'por_pagina' => $porPag,
    ]);
}

function buscarCliente(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $stmt = conectar()->prepare('SELECT * FROM clientes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $c = $stmt->fetch();
    if (!$c) responder(false, 'Cliente não encontrado.', null, 404);
    responder(true, 'OK', $c);
}

function criarCliente(): void {
    $d = lerJson();
    validarCliente($d);
    $pdo = conectar();
    $chk = $pdo->prepare('SELECT id FROM clientes WHERE documento = :doc');
    $chk->execute([':doc' => trim($d['documento'])]);
    if ($chk->fetch()) responder(false, 'Documento já cadastrado.', null, 409);

    $sql = "INSERT INTO clientes
              (tipo_pessoa, nome_completo, documento, data_nasc, limite_credito, categoria,
               telefone, celular, email, cep, logradouro, numero, complemento, bairro, cidade, estado)
            VALUES
              (:tipo_pessoa, :nome_completo, :documento, :data_nasc, :limite_credito, :categoria,
               :telefone, :celular, :email, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado)";
    $pdo->prepare($sql)->execute(mapCliente($d));
    responder(true, 'Cliente cadastrado.', ['id' => (int)$pdo->lastInsertId()], 201);
}

function atualizarCliente(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    validarCliente($d);

    $pdo    = conectar();
    $chk    = $pdo->prepare('SELECT id FROM clientes WHERE id = :id');
    $chk->execute([':id' => $id]);
    if (!$chk->fetch()) responder(false, 'Cliente não encontrado.', null, 404);

    $chkDoc = $pdo->prepare('SELECT id FROM clientes WHERE documento = :doc AND id <> :id');
    $chkDoc->execute([':doc' => trim($d['documento']), ':id' => $id]);
    if ($chkDoc->fetch()) responder(false, 'Documento já usado por outro cliente.', null, 409);

    $sql = "UPDATE clientes SET
              tipo_pessoa   = :tipo_pessoa,
              nome_completo = :nome_completo,
              documento     = :documento,
              data_nasc     = :data_nasc,
              limite_credito= :limite_credito,
              categoria     = :categoria,
              telefone      = :telefone,
              celular       = :celular,
              email         = :email,
              cep           = :cep,
              logradouro    = :logradouro,
              numero        = :numero,
              complemento   = :complemento,
              bairro        = :bairro,
              cidade        = :cidade,
              estado        = :estado,
              atualizado_em = NOW()
            WHERE id = :id";
    $params = mapCliente($d);
    $params[':id'] = $id;
    $pdo->prepare($sql)->execute($params);
    responder(true, 'Cliente atualizado.');
}

function excluirCliente(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    $pdo = conectar();

    // Impede excluir cliente com pedidos vinculados
    $chkPed = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE cliente_id = :id');
    $chkPed->execute([':id' => $id]);
    if ((int)$chkPed->fetchColumn() > 0) {
        responder(false, 'Cliente possui pedidos vinculados e não pode ser excluído.', null, 409);
    }

    $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) responder(false, 'Cliente não encontrado.', null, 404);
    responder(true, 'Cliente excluído.');
}

function validarCliente(array $d): void {
    $obrig = ['tipo_pessoa', 'nome_completo', 'documento', 'telefone', 'email', 'cep', 'logradouro', 'cidade'];
    $falta = array_filter($obrig, fn($k) => empty(trim((string)($d[$k] ?? ''))));
    if ($falta) responder(false, 'Campos obrigatórios ausentes: ' . implode(', ', $falta), null, 422);

    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        responder(false, 'E-mail inválido.', null, 422);
    }

    if (!in_array($d['tipo_pessoa'], ['PF', 'PJ'], true)) {
        responder(false, 'Tipo de pessoa inválido. Use PF ou PJ.', null, 422);
    }

    // Valida limite de crédito
    if (isset($d['limite_credito']) && $d['limite_credito'] !== '' && (float)$d['limite_credito'] < 0) {
        responder(false, 'Limite de crédito não pode ser negativo.', null, 422);
    }

    // Valida formato básico do documento
    $doc = preg_replace('/\D/', '', $d['documento']);
    if ($d['tipo_pessoa'] === 'PF' && strlen($doc) !== 11) {
        responder(false, 'CPF deve ter 11 dígitos numéricos.', null, 422);
    }
    if ($d['tipo_pessoa'] === 'PJ' && strlen($doc) !== 14) {
        responder(false, 'CNPJ deve ter 14 dígitos numéricos.', null, 422);
    }
}

function mapCliente(array $d): array {
    return [
        ':tipo_pessoa'    => $d['tipo_pessoa'],
        ':nome_completo'  => trim($d['nome_completo']),
        ':documento'      => trim($d['documento']),
        ':data_nasc'      => !empty($d['data_nasc'])       ? $d['data_nasc']                 : null,
        ':limite_credito' => (isset($d['limite_credito']) && $d['limite_credito'] !== '')
                                ? (float)$d['limite_credito'] : null,
        ':categoria'      => !empty($d['categoria'])       ? $d['categoria']                 : null,
        ':telefone'       => trim($d['telefone']),
        ':celular'        => !empty(trim($d['celular'] ?? ''))    ? trim($d['celular'])       : null,
        ':email'          => strtolower(trim($d['email'])),
        ':cep'            => trim($d['cep']),
        ':logradouro'     => trim($d['logradouro']),
        ':numero'         => !empty(trim($d['numero'] ?? ''))     ? trim($d['numero'])        : null,
        ':complemento'    => !empty(trim($d['complemento'] ?? ''))? trim($d['complemento'])   : null,
        ':bairro'         => !empty(trim($d['bairro'] ?? ''))     ? trim($d['bairro'])        : null,
        ':cidade'         => trim($d['cidade']),
        ':estado'         => !empty($d['estado'])          ? $d['estado']                    : null,
    ];
}
