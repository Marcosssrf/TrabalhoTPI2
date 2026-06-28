<?php
require_once 'db.php';
exigirLogin();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        if ($action === 'buscar')  buscarProduto();
        if ($action === 'options') listarOptions();
        listarProdutos();
        break;
    case 'POST':   criarProduto();     break;
    case 'PUT':    atualizarProduto(); break;
    case 'DELETE': excluirProduto();   break;
    default: responder(false, 'Método não suportado.', null, 405);
}

function listarProdutos(): void {
    $pdo    = conectar();
    $search = trim($_GET['search'] ?? '');
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $porPag = max(1, min(100, (int)($_GET['por_pagina'] ?? 10)));
    $offset = ($pagina - 1) * $porPag;

    $where = ''; $params = [];
    if ($search !== '') {
        $where = "WHERE p.codigo LIKE :s OR p.nome LIKE :s OR p.categoria LIKE :s";
        $params[':s'] = "%{$search}%";
    }

    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM produtos p {$where}");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();

    $sql  = "SELECT p.id, p.codigo, p.nome, p.categoria, p.unidade,
                    p.preco_venda, p.estoque_atual, p.estoque_min, p.ativo,
                    f.razao_social AS fornecedor_nome
             FROM produtos p
             LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
             {$where} ORDER BY p.nome LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $porPag, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    responder(true, 'OK', $stmt->fetchAll(), 200, [
        'total' => $total, 'pagina' => $pagina, 'por_pagina' => $porPag,
    ]);
}

function buscarProduto(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $stmt = conectar()->prepare('SELECT * FROM produtos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) responder(false, 'Produto não encontrado.', null, 404);
    responder(true, 'OK', $row);
}

// Retorna lista simplificada para preencher <select> em outras páginas
function listarOptions(): void {
    $stmt = conectar()->query('SELECT id, codigo, nome, preco_venda, estoque_atual, unidade
                               FROM produtos WHERE ativo = 1 ORDER BY nome');
    responder(true, 'OK', $stmt->fetchAll());
}

function criarProduto(): void {
    $d = lerJson(); validarProduto($d);
    $pdo = conectar();
    $chk = $pdo->prepare('SELECT id FROM produtos WHERE codigo = :c');
    $chk->execute([':c' => $d['codigo']]);
    if ($chk->fetch()) responder(false, 'Código já cadastrado.', null, 409);

    $sql = "INSERT INTO produtos
              (codigo,cod_barras,nome,categoria,unidade,preco_custo,preco_venda,
               estoque_atual,estoque_min,fornecedor_id,obs,ativo)
            VALUES
              (:codigo,:cod_barras,:nome,:categoria,:unidade,:preco_custo,:preco_venda,
               :estoque_atual,:estoque_min,:fornecedor_id,:obs,:ativo)";
    $pdo->prepare($sql)->execute(mapProduto($d));
    responder(true, 'Produto cadastrado.', ['id' => (int)$pdo->lastInsertId()], 201);
}

function atualizarProduto(): void {
    $d  = lerJson(); $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    validarProduto($d);
    $pdo = conectar();
    $chk = $pdo->prepare('SELECT id FROM produtos WHERE id = :id');
    $chk->execute([':id' => $id]);
    if (!$chk->fetch()) responder(false, 'Produto não encontrado.', null, 404);
    $chkCod = $pdo->prepare('SELECT id FROM produtos WHERE codigo = :c AND id <> :id');
    $chkCod->execute([':c' => $d['codigo'], ':id' => $id]);
    if ($chkCod->fetch()) responder(false, 'Código já usado por outro produto.', null, 409);

    $sql = "UPDATE produtos SET
              codigo=:codigo,cod_barras=:cod_barras,nome=:nome,categoria=:categoria,
              unidade=:unidade,preco_custo=:preco_custo,preco_venda=:preco_venda,
              estoque_atual=:estoque_atual,estoque_min=:estoque_min,
              fornecedor_id=:fornecedor_id,obs=:obs,ativo=:ativo,atualizado_em=NOW()
            WHERE id=:id";
    $params = mapProduto($d); $params[':id'] = $id;
    $pdo->prepare($sql)->execute($params);
    responder(true, 'Produto atualizado.');
}

function excluirProduto(): void {
    $d = lerJson(); $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    // Verifica se há movimentações vinculadas
    $chk = conectar()->prepare('SELECT COUNT(*) FROM movimentacoes WHERE produto_id = :id');
    $chk->execute([':id' => $id]);
    if ((int)$chk->fetchColumn() > 0) {
        responder(false, 'Produto possui movimentações e não pode ser excluído. Desative-o.', null, 409);
    }
    $stmt = conectar()->prepare('DELETE FROM produtos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) responder(false, 'Produto não encontrado.', null, 404);
    responder(true, 'Produto excluído.');
}

function validarProduto(array $d): void {
    $obrig = ['codigo','nome','categoria','unidade'];
    $falta = array_filter($obrig, fn($k) => empty($d[$k]));
    if ($falta) responder(false, 'Campos obrigatórios: '.implode(', ',$falta), null, 422);

    $atual = isset($d['estoque_atual']) && $d['estoque_atual'] !== '' ? (int)$d['estoque_atual'] : 0;
    $min   = isset($d['estoque_min'])   && $d['estoque_min']   !== '' ? (int)$d['estoque_min']   : 0;
    if ($min < 0) responder(false, 'Estoque mínimo não pode ser negativo.', null, 422);
    if ($atual < 0) responder(false, 'Estoque atual não pode ser negativo.', null, 422);
    if ($min > $atual) responder(false, "Estoque mínimo ({$min}) não pode ser maior que o estoque atual ({$atual}).", null, 422);
}

function mapProduto(array $d): array {
    return [
        ':codigo'        => trim($d['codigo']),
        ':cod_barras'    => $d['cod_barras']   ?? null,
        ':nome'          => $d['nome'],
        ':categoria'     => $d['categoria'],
        ':unidade'       => $d['unidade'],
        ':preco_custo'   => isset($d['preco_custo'])  && $d['preco_custo']  !== '' ? (float)$d['preco_custo']  : 0,
        ':preco_venda'   => isset($d['preco_venda'])  && $d['preco_venda']  !== '' ? (float)$d['preco_venda']  : 0,
        ':estoque_atual' => isset($d['estoque_atual']) && $d['estoque_atual'] !== '' ? (int)$d['estoque_atual'] : 0,
        ':estoque_min'   => isset($d['estoque_min'])  && $d['estoque_min']  !== '' ? (int)$d['estoque_min']    : 0,
        ':fornecedor_id' => !empty($d['fornecedor_id']) ? (int)$d['fornecedor_id'] : null,
        ':obs'           => $d['obs']  ?? null,
        ':ativo'         => isset($d['ativo']) ? (int)(bool)$d['ativo'] : 1,
    ];
}
