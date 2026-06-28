<?php
require_once 'db.php';
exigirLogin();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        if ($action === 'buscar') buscarMovimentacao();
        listarMovimentacoes();
        break;
    case 'POST':   criarMovimentacao();   break;
    case 'DELETE': excluirMovimentacao(); break;
    default: responder(false, 'Método não suportado.', null, 405);
}

function listarMovimentacoes(): void {
    $pdo    = conectar();
    $search = trim($_GET['search'] ?? '');
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $porPag = max(1, min(100, (int)($_GET['por_pagina'] ?? 10)));
    $offset = ($pagina - 1) * $porPag;

    $where = ''; $params = [];
    if ($search !== '') {
        $where = "WHERE pr.nome LIKE :s OR m.motivo LIKE :s OR m.responsavel LIKE :s OR m.tipo LIKE :s";
        $params[':s'] = "%{$search}%";
    }

    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM movimentacoes m
                             LEFT JOIN produtos pr ON pr.id = m.produto_id {$where}");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();

    $sql  = "SELECT m.id, m.tipo, m.quantidade, m.unidade, m.motivo, m.data_mov,
                    m.num_doc, m.responsavel, m.saldo_apos,
                    pr.nome AS produto_nome, pr.codigo AS produto_codigo
             FROM movimentacoes m
             LEFT JOIN produtos pr ON pr.id = m.produto_id
             {$where} ORDER BY m.data_mov DESC, m.id DESC
             LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $porPag, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    responder(true, 'OK', $stmt->fetchAll(), 200, [
        'total' => $total, 'pagina' => $pagina, 'por_pagina' => $porPag,
    ]);
}

function buscarMovimentacao(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $stmt = conectar()->prepare('SELECT m.*, pr.nome AS produto_nome, pr.codigo AS produto_codigo
                                 FROM movimentacoes m
                                 LEFT JOIN produtos pr ON pr.id = m.produto_id
                                 WHERE m.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) responder(false, 'Movimentação não encontrada.', null, 404);
    responder(true, 'OK', $row);
}

function criarMovimentacao(): void {
    $d = lerJson();
    validarMovimentacao($d);

    $pdo       = conectar();
    $produtoId = (int)$d['produto_id'];
    $tipo      = $d['tipo'];
    $qtd       = (int)$d['quantidade'];

    $pdo->beginTransaction();
    try {
        // Bloqueia linha do produto para evitar race condition
        $stmtProd = $pdo->prepare('SELECT nome, estoque_atual, unidade FROM produtos WHERE id = :id FOR UPDATE');
        $stmtProd->execute([':id' => $produtoId]);
        $prod = $stmtProd->fetch();

        if (!$prod) {
            $pdo->rollBack();
            responder(false, 'Produto não encontrado.', null, 404);
        }

        $saldoAtual = (int)$prod['estoque_atual'];

        if ($tipo === 'Saida' && $qtd > $saldoAtual) {
            $pdo->rollBack();
            responder(false, "Saldo insuficiente para \"{$prod['nome']}\". Disponível: {$saldoAtual}, solicitado: {$qtd}.", null, 409);
        }

        $novoSaldo = $tipo === 'Entrada' ? $saldoAtual + $qtd : $saldoAtual - $qtd;

        // Impede saldo negativo por segurança extra
        if ($novoSaldo < 0) {
            $pdo->rollBack();
            responder(false, 'Operação resultaria em saldo negativo.', null, 409);
        }

        $pdo->prepare('UPDATE produtos SET estoque_atual = :s, atualizado_em = NOW() WHERE id = :id')
            ->execute([':s' => $novoSaldo, ':id' => $produtoId]);

        $pdo->prepare("INSERT INTO movimentacoes
                         (tipo, produto_id, quantidade, unidade, motivo, data_mov, num_doc, responsavel, obs, saldo_apos)
                       VALUES
                         (:tipo, :produto_id, :quantidade, :unidade, :motivo, :data_mov, :num_doc, :responsavel, :obs, :saldo_apos)")
            ->execute([
                ':tipo'        => $tipo,
                ':produto_id'  => $produtoId,
                ':quantidade'  => $qtd,
                ':unidade'     => !empty(trim($d['unidade'] ?? '')) ? trim($d['unidade']) : $prod['unidade'],
                ':motivo'      => $d['motivo'],
                ':data_mov'    => $d['data_mov'],
                ':num_doc'     => !empty(trim($d['num_doc'] ?? '')) ? trim($d['num_doc']) : null,
                ':responsavel' => trim($d['responsavel']),
                ':obs'         => !empty(trim($d['obs'] ?? '')) ? trim($d['obs']) : null,
                ':saldo_apos'  => $novoSaldo,
            ]);

        $pdo->commit();
        responder(true, 'Movimentação registrada.', [
            'id'         => (int)$pdo->lastInsertId(),
            'saldo_apos' => $novoSaldo,
        ], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(false, 'Erro: ' . $e->getMessage(), null, 500);
    }
}

function excluirMovimentacao(): void {
    if (!isAdmin()) {
        responder(false, 'Apenas administradores podem excluir movimentações.', null, 403);
    }

    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    $pdo = conectar();
    $mov = $pdo->prepare('SELECT * FROM movimentacoes WHERE id = :id');
    $mov->execute([':id' => $id]);
    $m = $mov->fetch();
    if (!$m) responder(false, 'Movimentação não encontrada.', null, 404);

    $pdo->beginTransaction();
    try {
        // Calcula delta para reverter: Entrada vira -, Saída vira +
        $delta = $m['tipo'] === 'Entrada' ? -(int)$m['quantidade'] : (int)$m['quantidade'];

        // Verifica que reverter não vai gerar saldo negativo
        $saldoAtual = (int)$pdo->query("SELECT estoque_atual FROM produtos WHERE id = {$m['produto_id']}")->fetchColumn();
        if ($saldoAtual + $delta < 0) {
            $pdo->rollBack();
            responder(false, 'Não é possível excluir: o estorno resultaria em saldo negativo.', null, 409);
        }

        $pdo->prepare('UPDATE produtos SET estoque_atual = estoque_atual + :d, atualizado_em = NOW() WHERE id = :id')
            ->execute([':d' => $delta, ':id' => $m['produto_id']]);

        $pdo->prepare('DELETE FROM movimentacoes WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
        responder(true, 'Movimentação excluída e estoque revertido.');
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(false, 'Erro: ' . $e->getMessage(), null, 500);
    }
}

function validarMovimentacao(array $d): void {
    $obrig = ['tipo', 'produto_id', 'quantidade', 'motivo', 'data_mov', 'responsavel'];
    $falta = array_filter($obrig, fn($k) => empty($d[$k]));
    if ($falta) responder(false, 'Campos obrigatórios: ' . implode(', ', $falta), null, 422);

    if (!in_array($d['tipo'], ['Entrada', 'Saida'], true)) {
        responder(false, 'Tipo inválido. Use "Entrada" ou "Saida".', null, 422);
    }

    if ((int)$d['quantidade'] <= 0) {
        responder(false, 'Quantidade deve ser maior que zero.', null, 422);
    }

    // Valida que a data não é futura
    if ($d['data_mov'] > date('Y-m-d')) {
        responder(false, 'Data da movimentação não pode ser futura.', null, 422);
    }
}
