<?php
require_once 'db.php';
exigirLogin();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        if ($action === 'buscar') buscarPedido();
        listarPedidos();
        break;
    case 'POST':   criarPedido();     break;
    case 'PUT':    atualizarPedido(); break;
    case 'DELETE': excluirPedido();   break;
    default: responder(false, 'Método não suportado.', null, 405);
}

// ── Listar ────────────────────────────────────────────────────
function listarPedidos(): void {
    $pdo    = conectar();
    $search = trim($_GET['search'] ?? '');
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $porPag = max(1, min(100, (int)($_GET['por_pagina'] ?? 10)));
    $offset = ($pagina - 1) * $porPag;

    $where = ''; $params = [];
    if ($search !== '') {
        $where = "WHERE p.num_pedido LIKE :s OR c.nome_completo LIKE :s OR p.status LIKE :s";
        $params[':s'] = "%{$search}%";
    }

    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM pedidos p
                             LEFT JOIN clientes c ON c.id = p.cliente_id {$where}");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();

    $sql  = "SELECT p.id, p.num_pedido, p.data_pedido, p.tipo, p.status,
                    p.total, p.forma_pag, c.nome_completo AS cliente_nome
             FROM pedidos p
             LEFT JOIN clientes c ON c.id = p.cliente_id
             {$where} ORDER BY p.data_pedido DESC, p.id DESC
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

// ── Buscar pedido com itens ───────────────────────────────────
function buscarPedido(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT p.*, c.nome_completo AS cliente_nome
                           FROM pedidos p
                           LEFT JOIN clientes c ON c.id = p.cliente_id
                           WHERE p.id = :id');
    $stmt->execute([':id' => $id]);
    $pedido = $stmt->fetch();
    if (!$pedido) responder(false, 'Pedido não encontrado.', null, 404);

    $itens = $pdo->prepare('SELECT pi.*, pr.nome AS produto_nome
                            FROM pedido_itens pi
                            LEFT JOIN produtos pr ON pr.id = pi.produto_id
                            WHERE pi.pedido_id = :id');
    $itens->execute([':id' => $id]);
    $pedido['itens'] = $itens->fetchAll();
    responder(true, 'OK', $pedido);
}

// ── Criar pedido ──────────────────────────────────────────────
function criarPedido(): void {
    $d = lerJson();
    validarPedido($d);
    $pdo = conectar();

    // Gera número sequencial
    if (empty($d['num_pedido'])) {
        $ano = date('Y');
        $max = (int)$pdo->query(
            "SELECT MAX(CAST(SUBSTRING(num_pedido, 10) AS UNSIGNED))
             FROM pedidos WHERE num_pedido LIKE 'PED-{$ano}-%'"
        )->fetchColumn();
        $d['num_pedido'] = 'PED-' . $ano . '-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
    }

    $chk = $pdo->prepare('SELECT id FROM pedidos WHERE num_pedido = :n');
    $chk->execute([':n' => $d['num_pedido']]);
    if ($chk->fetch()) responder(false, 'Número de pedido já existe.', null, 409);

    $pdo->beginTransaction();
    try {
        // Valida e bloqueia estoque dentro da transação (apenas Venda)
        if (($d['tipo'] ?? '') === 'Venda' && !empty($d['itens'])) {
            $erros = verificarEstoque($pdo, $d['itens']);
            if ($erros) {
                $pdo->rollBack();
                responder(false, 'Estoque insuficiente: ' . implode('; ', $erros), null, 409);
            }
        }

        // Insere cabeçalho
        $pdo->prepare(
            "INSERT INTO pedidos
               (num_pedido, data_pedido, tipo, cliente_id, data_entrega,
                forma_pag, desconto, total, obs, status)
             VALUES
               (:num_pedido, :data_pedido, :tipo, :cliente_id, :data_entrega,
                :forma_pag, :desconto, :total, :obs, :status)"
        )->execute([
            ':num_pedido'   => $d['num_pedido'],
            ':data_pedido'  => $d['data_pedido'],
            ':tipo'         => $d['tipo'],
            ':cliente_id'   => !empty($d['cliente_id'])   ? (int)$d['cliente_id']   : null,
            ':data_entrega' => !empty($d['data_entrega'])  ? $d['data_entrega']       : null,
            ':forma_pag'    => $d['forma_pag'],
            ':desconto'     => (float)($d['desconto'] ?? 0),
            ':total'        => (float)($d['total']    ?? 0),
            ':obs'          => $d['obs'] ?? null,
            ':status'       => $d['status'] ?? 'Aberto',
        ]);
        $pedidoId = (int)$pdo->lastInsertId();

        if (!empty($d['itens']) && is_array($d['itens'])) {
            inserirItens($pdo, $pedidoId, $d['itens']);

            // Desconta estoque e registra movimentações somente para Venda
            if (($d['tipo'] ?? '') === 'Venda') {
                descontarEstoque($pdo, $d['itens'], $d['num_pedido']);
            }
        }

        $pdo->commit();
        responder(true, 'Pedido criado.', ['id' => $pedidoId, 'num_pedido' => $d['num_pedido']], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(false, 'Erro ao salvar pedido: ' . $e->getMessage(), null, 500);
    }
}

// ── Atualizar pedido ──────────────────────────────────────────
function atualizarPedido(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);
    validarPedido($d);

    $pdo = conectar();

    // Busca dados originais do pedido
    $orig = $pdo->prepare('SELECT tipo FROM pedidos WHERE id = :id');
    $orig->execute([':id' => $id]);
    $pedidoOrig = $orig->fetch();
    if (!$pedidoOrig) responder(false, 'Pedido não encontrado.', null, 404);

    // Busca itens originais para reverter estoque
    $stmtItOrig = $pdo->prepare(
        'SELECT produto_id, quantidade FROM pedido_itens WHERE pedido_id = :id AND produto_id IS NOT NULL'
    );
    $stmtItOrig->execute([':id' => $id]);
    $itensOriginais = $stmtItOrig->fetchAll();

    $pdo->beginTransaction();
    try {
        // 1. Reverte estoque dos itens antigos (se era Venda)
        if ($pedidoOrig['tipo'] === 'Venda' && !empty($itensOriginais)) {
            devolverEstoque($pdo, $itensOriginais);
        }

        // 2. Valida estoque para os novos itens (se vai ser Venda)
        if (($d['tipo'] ?? '') === 'Venda' && !empty($d['itens'])) {
            $erros = verificarEstoque($pdo, $d['itens']);
            if ($erros) {
                $pdo->rollBack();
                responder(false, 'Estoque insuficiente: ' . implode('; ', $erros), null, 409);
            }
        }

        // 3. Atualiza cabeçalho
        $pdo->prepare(
            "UPDATE pedidos SET
               data_pedido   = :data_pedido,
               tipo          = :tipo,
               cliente_id    = :cliente_id,
               data_entrega  = :data_entrega,
               forma_pag     = :forma_pag,
               desconto      = :desconto,
               total         = :total,
               obs           = :obs,
               status        = :status,
               atualizado_em = NOW()
             WHERE id = :id"
        )->execute([
            ':data_pedido'  => $d['data_pedido'],
            ':tipo'         => $d['tipo'],
            ':cliente_id'   => !empty($d['cliente_id'])   ? (int)$d['cliente_id']   : null,
            ':data_entrega' => !empty($d['data_entrega'])  ? $d['data_entrega']       : null,
            ':forma_pag'    => $d['forma_pag'],
            ':desconto'     => (float)($d['desconto'] ?? 0),
            ':total'        => (float)($d['total']    ?? 0),
            ':obs'          => $d['obs'] ?? null,
            ':status'       => $d['status'] ?? 'Aberto',
            ':id'           => $id,
        ]);

        // 4. Recria itens
        $pdo->prepare('DELETE FROM pedido_itens WHERE pedido_id = :id')->execute([':id' => $id]);
        if (!empty($d['itens']) && is_array($d['itens'])) {
            inserirItens($pdo, $id, $d['itens']);

            // 5. Desconta estoque dos novos itens (se for Venda)
            if (($d['tipo'] ?? '') === 'Venda') {
                descontarEstoque($pdo, $d['itens'], 'EDIT-PED-' . $id);
            }
        }

        $pdo->commit();
        responder(true, 'Pedido atualizado.');
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(false, 'Erro ao atualizar pedido: ' . $e->getMessage(), null, 500);
    }
}

// ── Excluir pedido ────────────────────────────────────────────
function excluirPedido(): void {
    $d  = lerJson();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) responder(false, 'ID inválido.', null, 400);

    $pdo = conectar();

    $orig = $pdo->prepare('SELECT tipo FROM pedidos WHERE id = :id');
    $orig->execute([':id' => $id]);
    $pedido = $orig->fetch();
    if (!$pedido) responder(false, 'Pedido não encontrado.', null, 404);

    $pdo->beginTransaction();
    try {
        // Reverte estoque se era Venda
        if ($pedido['tipo'] === 'Venda') {
            $stmtIt = $pdo->prepare(
                'SELECT produto_id, quantidade FROM pedido_itens WHERE pedido_id = :id AND produto_id IS NOT NULL'
            );
            $stmtIt->execute([':id' => $id]);
            $itens = $stmtIt->fetchAll();
            if ($itens) devolverEstoque($pdo, $itens);
        }

        $pdo->prepare('DELETE FROM pedido_itens WHERE pedido_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM pedidos WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
        responder(true, 'Pedido excluído' . ($pedido['tipo'] === 'Venda' ? ' e estoque revertido.' : '.'));
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(false, 'Erro: ' . $e->getMessage(), null, 500);
    }
}

// ── Helpers ───────────────────────────────────────────────────

/**
 * Verifica estoque para uma lista de itens.
 * DEVE ser chamado dentro de uma transação ativa (usa FOR UPDATE).
 * Agrupa itens do mesmo produto para checar o total de uma vez.
 */
function verificarEstoque(PDO $pdo, array $itens): array {
    // Agrupa quantidade por produto (mesmo produto pode aparecer em linhas diferentes)
    $mapa = [];
    foreach ($itens as $item) {
        $prodId = (int)($item['produto_id'] ?? 0);
        $qty    = (int)($item['quantidade'] ?? 0);
        if (!$prodId || $qty <= 0) continue;
        $mapa[$prodId] = ($mapa[$prodId] ?? 0) + $qty;
    }

    $erros = [];
    foreach ($mapa as $prodId => $totalQty) {
        $stmt = $pdo->prepare('SELECT nome, estoque_atual FROM produtos WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $prodId]);
        $prod = $stmt->fetch();
        if (!$prod) {
            $erros[] = "Produto ID {$prodId} não encontrado";
            continue;
        }
        if ($totalQty > (int)$prod['estoque_atual']) {
            $erros[] = "{$prod['nome']}: disponível {$prod['estoque_atual']}, solicitado {$totalQty}";
        }
    }
    return $erros;
}

/** Insere os itens na tabela pedido_itens */
function inserirItens(PDO $pdo, int $pedidoId, array $itens): void {
    $stmt = $pdo->prepare(
        "INSERT INTO pedido_itens
           (pedido_id, produto_id, descricao, quantidade, preco_unit, subtotal)
         VALUES
           (:pid, :prod_id, :desc, :qty, :punit, :sub)"
    );
    foreach ($itens as $item) {
        $qty   = (int)($item['quantidade']  ?? 1);
        $punit = (float)($item['preco_unit'] ?? 0);
        $stmt->execute([
            ':pid'     => $pedidoId,
            ':prod_id' => !empty($item['produto_id']) ? (int)$item['produto_id'] : null,
            ':desc'    => trim($item['descricao'] ?? ''),
            ':qty'     => $qty,
            ':punit'   => $punit,
            ':sub'     => round($qty * $punit, 2),  // recalcula para garantir consistência
        ]);
    }
}

/**
 * Desconta estoque dos produtos e registra movimentação de Saída.
 * Agrupa itens do mesmo produto antes de aplicar o desconto.
 */
function descontarEstoque(PDO $pdo, array $itens, string $numPedido): void {
    // Busca unidade de cada produto uma única vez
    $mapa = [];
    foreach ($itens as $item) {
        $prodId = (int)($item['produto_id'] ?? 0);
        $qty    = (int)($item['quantidade'] ?? 0);
        if (!$prodId || $qty <= 0) continue;
        $mapa[$prodId] = ($mapa[$prodId] ?? 0) + $qty;
    }

    $stmtProd = $pdo->prepare('SELECT nome, unidade, estoque_atual FROM produtos WHERE id = :id');
    $stmtUpd  = $pdo->prepare(
        'UPDATE produtos SET estoque_atual = estoque_atual - :qty, atualizado_em = NOW() WHERE id = :id'
    );
    $stmtMov  = $pdo->prepare(
        "INSERT INTO movimentacoes
           (tipo, produto_id, quantidade, unidade, motivo, data_mov, num_doc, responsavel, saldo_apos)
         VALUES
           ('Saida', :prod_id, :qty, :unidade, 'Venda', :data, :num_doc, 'Sistema', :saldo)"
    );

    foreach ($mapa as $prodId => $qty) {
        $stmtProd->execute([':id' => $prodId]);
        $prod = $stmtProd->fetch();
        if (!$prod) continue;

        $saldoApos = (int)$prod['estoque_atual'] - $qty;

        $stmtUpd->execute([':qty' => $qty, ':id' => $prodId]);
        $stmtMov->execute([
            ':prod_id' => $prodId,
            ':qty'     => $qty,
            ':unidade' => $prod['unidade'] ?: 'UN',
            ':data'    => date('Y-m-d'),
            ':num_doc' => $numPedido,
            ':saldo'   => $saldoApos,
        ]);
    }
}

/**
 * Devolve estoque dos itens (ao editar ou excluir Venda).
 * Agrupa itens do mesmo produto.
 */
function devolverEstoque(PDO $pdo, array $itens): void {
    $mapa = [];
    foreach ($itens as $item) {
        $prodId = (int)($item['produto_id'] ?? 0);
        $qty    = (int)($item['quantidade'] ?? 0);
        if (!$prodId || $qty <= 0) continue;
        $mapa[$prodId] = ($mapa[$prodId] ?? 0) + $qty;
    }

    $stmtProd = $pdo->prepare('SELECT unidade, estoque_atual FROM produtos WHERE id = :id');
    $stmtUpd  = $pdo->prepare(
        'UPDATE produtos SET estoque_atual = estoque_atual + :qty, atualizado_em = NOW() WHERE id = :id'
    );
    $stmtMov  = $pdo->prepare(
        "INSERT INTO movimentacoes
           (tipo, produto_id, quantidade, unidade, motivo, data_mov, responsavel, saldo_apos)
         VALUES
           ('Entrada', :prod_id, :qty, :unidade, 'Estorno de Venda', :data, 'Sistema', :saldo)"
    );

    foreach ($mapa as $prodId => $qty) {
        $stmtProd->execute([':id' => $prodId]);
        $prod = $stmtProd->fetch();
        if (!$prod) continue;

        $saldoApos = (int)$prod['estoque_atual'] + $qty;

        $stmtUpd->execute([':qty' => $qty, ':id' => $prodId]);
        $stmtMov->execute([
            ':prod_id' => $prodId,
            ':qty'     => $qty,
            ':unidade' => $prod['unidade'] ?: 'UN',
            ':data'    => date('Y-m-d'),
            ':saldo'   => $saldoApos,
        ]);
    }
}

// ── Validação ─────────────────────────────────────────────────
function validarPedido(array $d): void {
    $obrig = ['data_pedido', 'tipo', 'forma_pag'];
    $falta = array_filter($obrig, fn($k) => empty($d[$k]));
    if ($falta) responder(false, 'Campos obrigatórios: ' . implode(', ', $falta), null, 422);

    $tiposValidos = ['Venda', 'Devolução', 'Troca'];
    if (!in_array($d['tipo'], $tiposValidos, true)) {
        responder(false, 'Tipo inválido. Use: ' . implode(', ', $tiposValidos), null, 422);
    }

    // Desconto entre 0 e 100
    $desc = (float)($d['desconto'] ?? 0);
    if ($desc < 0 || $desc > 100) {
        responder(false, 'Desconto deve ser entre 0 e 100%.', null, 422);
    }

    // Deve ter ao menos um item
    if (empty($d['itens']) || !is_array($d['itens']) || count($d['itens']) === 0) {
        responder(false, 'O pedido deve ter ao menos um item.', null, 422);
    }

    // Valida cada item
    foreach ($d['itens'] as $i => $item) {
        $n = $i + 1;
        if ((int)($item['quantidade'] ?? 0) <= 0) {
            responder(false, "Item {$n}: quantidade deve ser maior que zero.", null, 422);
        }
        if ((float)($item['preco_unit'] ?? 0) < 0) {
            responder(false, "Item {$n}: preço unitário não pode ser negativo.", null, 422);
        }
    }
}
