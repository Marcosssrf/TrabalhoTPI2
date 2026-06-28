<?php
require_once 'db.php';
exigirLogin();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        if ($action === 'buscar') buscarFornecedor();
        listarFornecedores();
        break;
    case 'POST':   criarFornecedor();     break;
    case 'PUT':    atualizarFornecedor(); break;
    case 'DELETE': excluirFornecedor();   break;
    default: responder(false, 'Método não suportado.', null, 405);
}

function listarFornecedores(): void {
    $pdo    = conectar();
    $search = trim($_GET['search'] ?? '');
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $porPag = max(1, min(100, (int)($_GET['por_pagina'] ?? 10)));
    $offset = ($pagina - 1) * $porPag;

    $where = ''; $params = [];
    if ($search !== '') {
        $where = "WHERE razao_social LIKE :s OR cnpj LIKE :s OR cidade LIKE :s";
        $params[':s'] = "%{$search}%";
    }

    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM fornecedores {$where}");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();

    $sql  = "SELECT id, razao_social, nome_fantasia, cnpj, segmento, telefone, email, cidade, estado
             FROM fornecedores {$where} ORDER BY razao_social LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $porPag, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    responder(true, 'OK', $stmt->fetchAll(), 200, [
        'total' => $total, 'pagina' => $pagina, 'por_pagina' => $porPag,
    ]);
}

function buscarFornecedor(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $stmt = conectar()->prepare('SELECT * FROM fornecedores WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) responder(false, 'Fornecedor não encontrado.', null, 404);
    responder(true, 'OK', $row);
}

function criarFornecedor(): void {
    $d = lerJson();
    validarFornecedor($d);
    $pdo = conectar();

    $chk = $pdo->prepare('SELECT id FROM fornecedores WHERE cnpj = :cnpj');
    $chk->execute([':cnpj' => trim($d['cnpj'])]);
    if ($chk->fetch()) responder(false, 'CNPJ já cadastrado.', null, 409);

    $sql = "INSERT INTO fornecedores
              (razao_social, nome_fantasia, cnpj, ie, segmento, contato, cargo,
               telefone, celular, email, cep, logradouro, numero, bairro, cidade, estado,
               prazo_pagto, forma_pagto, obs)
            VALUES
              (:razao_social, :nome_fantasia, :cnpj, :ie, :segmento, :contato, :cargo,
               :telefone, :celular, :email, :cep, :logradouro, :numero, :bairro, :cidade, :estado,
               :prazo_pagto, :forma_pagto, :obs)";
    $pdo->prepare($sql)->execute(mapFornecedor($d));
    responder(true, 'Fornecedor cadastrado.', ['id' => (int)$pdo->lastInsertId()], 201);
}

function atualizarFornecedor(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    validarFornecedor($d);

    $pdo = conectar();
    $chk = $pdo->prepare('SELECT id FROM fornecedores WHERE id = :id');
    $chk->execute([':id' => $id]);
    if (!$chk->fetch()) responder(false, 'Fornecedor não encontrado.', null, 404);

    $chkCnpj = $pdo->prepare('SELECT id FROM fornecedores WHERE cnpj = :cnpj AND id <> :id');
    $chkCnpj->execute([':cnpj' => trim($d['cnpj']), ':id' => $id]);
    if ($chkCnpj->fetch()) responder(false, 'CNPJ já usado por outro fornecedor.', null, 409);

    $sql = "UPDATE fornecedores SET
              razao_social  = :razao_social,
              nome_fantasia = :nome_fantasia,
              cnpj          = :cnpj,
              ie            = :ie,
              segmento      = :segmento,
              contato       = :contato,
              cargo         = :cargo,
              telefone      = :telefone,
              celular       = :celular,
              email         = :email,
              cep           = :cep,
              logradouro    = :logradouro,
              numero        = :numero,
              bairro        = :bairro,
              cidade        = :cidade,
              estado        = :estado,
              prazo_pagto   = :prazo_pagto,
              forma_pagto   = :forma_pagto,
              obs           = :obs,
              atualizado_em = NOW()
            WHERE id = :id";
    $params = mapFornecedor($d);
    $params[':id'] = $id;
    $pdo->prepare($sql)->execute($params);
    responder(true, 'Fornecedor atualizado.');
}

function excluirFornecedor(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    $pdo = conectar();

    // Impede excluir fornecedor vinculado a produtos
    $chkProd = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE fornecedor_id = :id');
    $chkProd->execute([':id' => $id]);
    if ((int)$chkProd->fetchColumn() > 0) {
        responder(false, 'Fornecedor possui produtos vinculados. Desvincule-os antes de excluir.', null, 409);
    }

    $stmt = $pdo->prepare('DELETE FROM fornecedores WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) responder(false, 'Fornecedor não encontrado.', null, 404);
    responder(true, 'Fornecedor excluído.');
}

function validarFornecedor(array $d): void {
    $obrig = ['razao_social', 'cnpj', 'segmento', 'contato', 'telefone', 'cep', 'logradouro', 'cidade'];
    $falta = array_filter($obrig, fn($k) => empty(trim((string)($d[$k] ?? ''))));
    if ($falta) responder(false, 'Campos obrigatórios: ' . implode(', ', $falta), null, 422);

    // Valida CNPJ (14 dígitos numéricos)
    $cnpj = preg_replace('/\D/', '', $d['cnpj']);
    if (strlen($cnpj) !== 14) {
        responder(false, 'CNPJ deve ter 14 dígitos numéricos.', null, 422);
    }

    // Valida prazo de pagamento
    if (isset($d['prazo_pagto']) && $d['prazo_pagto'] !== '' && (int)$d['prazo_pagto'] < 0) {
        responder(false, 'Prazo de pagamento não pode ser negativo.', null, 422);
    }

    // Valida e-mail se preenchido
    if (!empty($d['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        responder(false, 'E-mail inválido.', null, 422);
    }
}

function mapFornecedor(array $d): array {
    $nn = fn($v) => !empty(trim((string)$v)) ? trim($v) : null;
    return [
        ':razao_social'  => trim($d['razao_social']),
        ':nome_fantasia' => $nn($d['nome_fantasia'] ?? ''),
        ':cnpj'          => trim($d['cnpj']),
        ':ie'            => $nn($d['ie'] ?? ''),
        ':segmento'      => $d['segmento'],
        ':contato'       => trim($d['contato']),
        ':cargo'         => $nn($d['cargo']  ?? ''),
        ':telefone'      => trim($d['telefone']),
        ':celular'       => $nn($d['celular'] ?? ''),
        ':email'         => $nn($d['email']   ?? ''),
        ':cep'           => trim($d['cep']),
        ':logradouro'    => trim($d['logradouro']),
        ':numero'        => $nn($d['numero']  ?? ''),
        ':bairro'        => $nn($d['bairro']  ?? ''),
        ':cidade'        => trim($d['cidade']),
        ':estado'        => $nn($d['estado']  ?? ''),
        ':prazo_pagto'   => (isset($d['prazo_pagto']) && $d['prazo_pagto'] !== '') ? (int)$d['prazo_pagto'] : null,
        ':forma_pagto'   => $nn($d['forma_pagto'] ?? ''),
        ':obs'           => $nn($d['obs']         ?? ''),
    ];
}
