-- ── Tabela ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clientes (
                                        id              INT             NOT NULL AUTO_INCREMENT PRIMARY KEY,
-- Identificação
                                        tipo_pessoa     CHAR(2)         NOT NULL,
    nome_completo   VARCHAR(200)    NOT NULL,
    documento       VARCHAR(18)     NOT NULL UNIQUE,
    data_nasc       DATE,
-- Comercial
    limite_credito  DECIMAL(12, 2)  DEFAULT 0.00,
    categoria       VARCHAR(20),
-- Contato
    telefone        VARCHAR(20)     NOT NULL,
    celular         VARCHAR(20),
    email           VARCHAR(150)    NOT NULL,
-- Endereço
    cep             VARCHAR(9)      NOT NULL,
    logradouro      VARCHAR(200)    NOT NULL,
    numero          VARCHAR(10),
    complemento     VARCHAR(100),
    bairro          VARCHAR(100),
    cidade          VARCHAR(100)    NOT NULL,
    estado          CHAR(2),
-- Auditoria
    criado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_tipo_pessoa CHECK (tipo_pessoa IN ('PF', 'PJ')),
    CONSTRAINT chk_categoria   CHECK (categoria   IN ('Bronze','Prata','Ouro','Platinum'))
    );

-- ── Índices ───────────────────────────────────────────────────────
CREATE INDEX idx_clientes_nome      ON clientes (nome_completo);
CREATE INDEX idx_clientes_documento ON clientes (documento);
CREATE INDEX idx_clientes_email     ON clientes (email);
CREATE INDEX idx_clientes_cidade    ON clientes (cidade);

-- ── Dados ─────────────────────────────────────────────────────────
INSERT INTO clientes
(tipo_pessoa, nome_completo, documento, data_nasc, limite_credito,
 categoria, telefone, celular, email,
 cep, logradouro, numero, bairro, cidade, estado)
VALUES
    ('PF', 'Ana Paula Ferreira',        '123.456.789-00',     '1990-04-15', 8000.00,
     'Prata',    '(34) 3214-5678', '(34) 99812-3456', 'ana.ferreira@email.com',
     '38400-000', 'Av. Rondon Pacheco',      '1500', 'Tibery',       'Uberlândia', 'MG'),

    ('PF', 'Carlos Eduardo Souza',      '987.654.321-00',     '1985-11-03', 25000.00,
     'Ouro',     '(34) 3301-9900', '(34) 99700-0011', 'carlos.souza@email.com',
     '38408-150', 'Rua Duque de Caxias',     '200',  'Centro',       'Uberlândia', 'MG'),

    ('PJ', 'Distribuidora Norte Ltda.', '12.345.678/0001-90', NULL,         60000.00,
     'Platinum', '(34) 3270-1122', NULL,               'contato@nordist.com.br',
     '38402-018', 'Av. João Naves de Ávila', '2121', 'Santa Mônica', 'Uberlândia', 'MG'),

    ('PF', 'Mariana Costa Lima',        '111.222.333-44',     '1995-07-22', 1500.00,
     'Bronze',   '(11) 4002-8922', '(11) 97654-3210', 'mari.lima@gmail.com',
     '01310-100', 'Av. Paulista',            '900',  'Bela Vista',   'São Paulo',  'SP'),

    ('PJ', 'Tech Solutions S/A',        '98.765.432/0001-10', NULL,         120000.00,
     'Platinum', '(11) 3030-4040', NULL,               'financeiro@techsolutions.com.br',
     '04578-000', 'Rua Funchal',             '418',  'Vila Olímpia', 'São Paulo',  'SP');

-- ── Confirma ──────────────────────────────────────────────────────
SELECT id, tipo_pessoa, nome_completo, documento, categoria, cidade
FROM clientes
ORDER BY id;