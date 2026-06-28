CREATE DATABASE IF NOT EXISTS estoque_manager
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE estoque_manager;

-- ── Usuários ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    pin           VARCHAR(10)  DEFAULT NULL,
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Usuário admin padrão (senha: password)
INSERT IGNORE INTO usuarios (username, password_hash, is_admin)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- ── Clientes ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clientes (
    id              INT             NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tipo_pessoa     CHAR(2)         NOT NULL,
    nome_completo   VARCHAR(200)    NOT NULL,
    documento       VARCHAR(18)     NOT NULL UNIQUE,
    data_nasc       DATE            DEFAULT NULL,
    limite_credito  DECIMAL(12,2)   DEFAULT 0.00,
    categoria       VARCHAR(20)     DEFAULT NULL,
    telefone        VARCHAR(20)     NOT NULL,
    celular         VARCHAR(20)     DEFAULT NULL,
    email           VARCHAR(150)    NOT NULL,
    cep             VARCHAR(9)      NOT NULL,
    logradouro      VARCHAR(200)    NOT NULL,
    numero          VARCHAR(10)     DEFAULT NULL,
    complemento     VARCHAR(100)    DEFAULT NULL,
    bairro          VARCHAR(100)    DEFAULT NULL,
    cidade          VARCHAR(100)    NOT NULL,
    estado          CHAR(2)         DEFAULT NULL,
    criado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_cli_nome      ON clientes (nome_completo);
CREATE INDEX idx_cli_documento ON clientes (documento);
CREATE INDEX idx_cli_email     ON clientes (email);

-- ── Fornecedores ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fornecedores (
    id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    razao_social  VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200) DEFAULT NULL,
    cnpj          VARCHAR(18)  NOT NULL UNIQUE,
    ie            VARCHAR(30)  DEFAULT NULL,
    segmento      VARCHAR(50)  NOT NULL,
    contato       VARCHAR(100) NOT NULL,
    cargo         VARCHAR(80)  DEFAULT NULL,
    telefone      VARCHAR(20)  NOT NULL,
    celular       VARCHAR(20)  DEFAULT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    cep           VARCHAR(9)   NOT NULL,
    logradouro    VARCHAR(200) NOT NULL,
    numero        VARCHAR(10)  DEFAULT NULL,
    bairro        VARCHAR(100) DEFAULT NULL,
    cidade        VARCHAR(100) NOT NULL,
    estado        CHAR(2)      DEFAULT NULL,
    prazo_pagto   INT          DEFAULT NULL,
    forma_pagto   VARCHAR(50)  DEFAULT NULL,
    obs           TEXT         DEFAULT NULL,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_for_cnpj ON fornecedores (cnpj);
CREATE INDEX idx_for_nome ON fornecedores (razao_social);

-- ── Produtos ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produtos (
    id             INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo         VARCHAR(20)    NOT NULL UNIQUE,
    cod_barras     VARCHAR(30)    DEFAULT NULL,
    nome           VARCHAR(200)   NOT NULL,
    categoria      VARCHAR(50)    NOT NULL,
    unidade        VARCHAR(20)    NOT NULL,
    preco_custo    DECIMAL(12,2)  DEFAULT 0.00,
    preco_venda    DECIMAL(12,2)  DEFAULT 0.00,
    estoque_atual  INT            NOT NULL DEFAULT 0,
    estoque_min    INT            NOT NULL DEFAULT 0,
    fornecedor_id  INT            DEFAULT NULL,
    obs            TEXT           DEFAULT NULL,
    ativo          TINYINT(1)     NOT NULL DEFAULT 1,
    criado_em      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_pro_codigo ON produtos (codigo);
CREATE INDEX idx_pro_nome   ON produtos (nome);

-- ── Pedidos ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pedidos (
    id            INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    num_pedido    VARCHAR(20)   NOT NULL UNIQUE,
    data_pedido   DATE          NOT NULL,
    tipo          VARCHAR(20)   NOT NULL,
    cliente_id    INT           DEFAULT NULL,
    data_entrega  DATE          DEFAULT NULL,
    forma_pag     VARCHAR(50)   NOT NULL,
    desconto      DECIMAL(5,2)  DEFAULT 0.00,
    total         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    obs           TEXT          DEFAULT NULL,
    status        VARCHAR(20)   NOT NULL DEFAULT 'Aberto',
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedido_itens (
    id          INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pedido_id   INT           NOT NULL,
    produto_id  INT           DEFAULT NULL,
    descricao   VARCHAR(200)  NOT NULL,
    quantidade  INT           NOT NULL,
    preco_unit  DECIMAL(12,2) NOT NULL,
    subtotal    DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (pedido_id)  REFERENCES pedidos(id)  ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Movimentações ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS movimentacoes (
    id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tipo          VARCHAR(10)  NOT NULL,
    produto_id    INT          NOT NULL,
    quantidade    INT          NOT NULL,
    unidade       VARCHAR(20)  NOT NULL DEFAULT 'UN',
    motivo        VARCHAR(50)  NOT NULL,
    data_mov      DATE         NOT NULL,
    num_doc       VARCHAR(30)  DEFAULT NULL,
    responsavel   VARCHAR(100) NOT NULL,
    obs           TEXT         DEFAULT NULL,
    saldo_apos    INT          NOT NULL,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
