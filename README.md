# 📦 EstoqueManager

Sistema de Controle de Estoque desenvolvido como trabalho prático da disciplina TPI2. A aplicação é composta por páginas HTML/CSS com lógica em JavaScript puro, sem dependência de frameworks ou back-end.

## Estrutura do Projeto

```
TrabalhoTPI2/
├── index.html              # Página inicial com menu de navegação
├── shared.css              # Folha de estilo compartilhada entre todas as páginas
└── pages/
    ├── produtos.html       # Formulário de cadastro de produtos
    ├── fornecedores.html   # Formulário de cadastro de fornecedores
    ├── clientes.html       # Formulário de cadastro de clientes
    ├── pedidos.html        # Formulário de registro de pedidos
    └── movimentacao.html   # Formulário de movimentação de estoque
```

## Módulos

| Módulo | Arquivo | Descrição |
|---|---|---|
| Produtos | `pages/produtos.html` | Cadastro de produtos com preço, categoria e controle de estoque |
| Fornecedores | `pages/fornecedores.html` | Cadastro de fornecedores com CNPJ, contato e condições comerciais |
| Clientes | `pages/clientes.html` | Cadastro de clientes físicos ou jurídicos com limite de crédito |
| Pedidos | `pages/pedidos.html` | Registro de pedidos de venda com múltiplos itens e formas de pagamento |
| Movimentação | `pages/movimentacao.html` | Registro de entradas e saídas com controle de saldo em tempo real |

## Como Executar

Basta abrir o arquivo `index.html` diretamente no navegador. Nenhuma instalação ou servidor é necessário.

```bash
# Opção 1 – abrir direto no navegador (duplo clique em index.html)

# Opção 2 – via extensão Live Server no VS Code
# Clique com botão direito em index.html → "Open with Live Server"
```

## Tecnologias

- **HTML5** – estrutura semântica dos formulários
- **CSS3** – layout responsivo com grid e flexbox
- **JavaScript (ES6)** – validação, cálculos e interatividade no cliente

## Colaboradores

| Aluno | Branch |
|---|---|
| Marcos | `marcos` |
| Otávio | `otavio` |
| Pitu | `pitu` |
| Vhmazza | `vhmazza` |

## Fluxo Git Utilizado

```bash
# 1. Inicialização
git init
git add .
git commit -m "feat: estrutura inicial do projeto"

# 2. Conexão com o GitHub
git remote add origin https://github.com/Marcosssrf/TrabalhoTPI2.git
git branch -M main
git push -u origin main

# 3. Criação das branches dos alunos
git checkout -b marcos
git checkout -b otavio
git checkout -b pitu
git checkout -b vhmazza

# 4. Cada aluno commita na sua branch
git checkout <branch-do-aluno>
git add .
git commit -m "feat: descrição da contribuição"
git push

# 5. Baixar as branches remotas localmente
git fetch
git checkout -b marcos origin/marcos
git checkout -b pitu origin/pitu
git checkout -b otavio origin/otavio
git checkout -b vhmazza origin/vhmazza

# 6. Merge na main
git checkout main
git merge marcos --no-ff -m "merge: marcos"
git merge pitu --no-ff -m "merge: pitu"
git merge otavio --no-ff -m "merge: otavio"
git merge vhmazza --no-ff -m "merge: vhmazza"
git push origin main
```
