# EstoqueManager — TPI2 — Guia de Instalação e Uso

## Visão Geral das Mudanças

Esta versão atende aos requisitos **10 e 11** do trabalho:

| Requisito | O que foi implementado |
|-----------|------------------------|
| 10.1 | Todas as telas comunicam com o banco via PHP + PDO (MySQL) |
| 10.2 | CRUD completo para **todas** as entidades (Produtos, Fornecedores, Clientes, Pedidos, Movimentações, Usuários) |
| 11.1 | Login consulta tabela `usuarios` no banco; senha armazenada com `password_hash` (bcrypt) |
| 11.2 | Sessão PHP (`$_SESSION`) guarda o usuário logado; todas as APIs verificam a sessão |

---

## Estrutura de Arquivos

```
TrabalhoTPI2/
├── login.html                  ← Página de login (entrada do sistema)
├── index.html                  ← Dashboard (requer login)
├── shared.css
└── pages/
    ├── db.php                  ← Conexão PDO + helpers compartilhados
    ├── auth_api.php            ← API de autenticação (login/logout/check)
    ├── auth.js                 ← Guard de sessão para todas as páginas
    ├── schema.sql              ← SQL completo (todas as tabelas)
    │
    ├── clientes.html / clientes.js / clientes_api.php
    ├── fornecedores.html       / fornecedores_api.php
    ├── produtos.html           / produtos_api.php
    ├── pedidos.html            / pedidos_api.php
    ├── movimentacao.html       / movimentacao_api.php
    └── usuarios.html           / usuarios_api.php
```

---

## Pré-requisitos

- PHP 8.0+ com extensões `pdo`, `pdo_mysql`, `openssl`
- MySQL 5.7+ ou MariaDB 10.3+
- Servidor web local: **XAMPP**, **Laragon**, **WAMP**, ou similar

---

## Passo a Passo de Instalação

### 1. Copiar os arquivos

Copie a pasta `TrabalhoTPI2/` para dentro do diretório público do seu servidor:

```
XAMPP  → C:\xampp\htdocs\TrabalhoTPI2\
Laragon → C:\laragon\www\TrabalhoTPI2\
```

### 2. Criar o banco de dados

Abra o **phpMyAdmin** (ou MySQL Workbench) e execute o arquivo:

```
pages/schema.sql
```

Ele cria o banco `estoque_manager` com todas as tabelas e um usuário admin padrão.

### 3. Ajustar credenciais do banco

Abra `pages/db.php` e altere se necessário:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'estoque_manager');
define('DB_USER', 'root');
define('DB_PASS', '');        // sua senha do MySQL
```

### 4. Acessar o sistema

Abra no navegador:

```
http://localhost/TrabalhoTPI2/login.html
```

---

## Credenciais Padrão

| Campo    | Valor      |
|----------|------------|
| Usuário  | `admin`    |
| Senha    | `password` |

> ⚠️ **Troque a senha após o primeiro acesso!**
> A senha padrão `password` é o hash `$2y$10$92IXUNpkjO0rOQ5...` inserido no `schema.sql`.

---

## Como Funciona a Autenticação

### Fluxo

```
Usuário acessa qualquer página
    ↓
auth.js chama auth_api.php?action=check
    ↓
PHP verifica $_SESSION['usuario_id']
    ↓
Não autenticado → redireciona para login.html
Autenticado     → exibe a página normalmente
```

### Login por usuário ou PIN

- **Aba "Usuário / Senha"**: informe o nome de usuário + senha
- **Aba "PIN / Senha"**: informe o PIN numérico + senha

### Sessão

O PHP usa `session_start()` em todo arquivo de API. Ao fazer login com sucesso:

```php
$_SESSION['usuario_id']    = $row['id'];
$_SESSION['usuario_nome']  = $row['username'];
$_SESSION['usuario_admin'] = (bool)$row['admin'];
```

---

## Perfis de Usuário

| Permissão                | Usuário Comum | Administrador |
|--------------------------|:---:|:---:|
| Cadastrar / editar registros | ✅ | ✅ |
| Excluir clientes, produtos, fornecedores, pedidos | ✅ | ✅ |
| Excluir movimentações (reverte estoque) | ❌ | ✅ |
| Excluir usuários | ❌ | ✅ |
| Definir perfil "admin" ao criar usuário | ❌ | ✅ |

---

## Endpoints das APIs

Todos os endpoints seguem o padrão REST e exigem sessão ativa:

| Endpoint | GET | POST | PUT | DELETE |
|----------|-----|------|-----|--------|
| `auth_api.php?action=check` | Verifica sessão | — | — | — |
| `auth_api.php?action=login` | — | Login | — | — |
| `auth_api.php?action=logout` | — | Logout | — | — |
| `usuarios_api.php` | Lista / busca | Cadastra | Atualiza | Exclui |
| `clientes_api.php` | Lista / busca | Cadastra | Atualiza | Exclui |
| `fornecedores_api.php` | Lista / busca | Cadastra | Atualiza | Exclui |
| `produtos_api.php` | Lista / busca | Cadastra | Atualiza | Exclui |
| `pedidos_api.php` | Lista / busca | Cadastra | Atualiza | Exclui |
| `movimentacao_api.php` | Lista / busca | Registra | — | Exclui (admin) |

---

## Observações Técnicas

- Senhas armazenadas com **bcrypt** (`password_hash` / `password_verify`)
- Queries usam **PDO com prepared statements** (proteção contra SQL injection)
- Movimentações atualizam o `estoque_atual` em transação com `FOR UPDATE` (evita race condition)
- Exclusão de movimentação **reverte automaticamente** o saldo do produto
- `auth.js` detecta automaticamente se está em `/pages/` ou na raiz para ajustar os caminhos das APIs
