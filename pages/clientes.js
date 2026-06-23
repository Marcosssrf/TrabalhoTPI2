'use strict';

const API_URL  = './clientes_api.php';
const POR_PAG  = 10;

let paginaAtual   = 1;
let totalPaginas  = 1;
let idParaExcluir = null;

const campoPesquisa     = () => document.getElementById('campoPesquisa');
const tabelaBody        = () => document.getElementById('tabelaClientesBody');
const resultadoDiv      = () => document.getElementById('resultadoPesquisa');
const semResultadosP    = () => document.getElementById('semResultados');
const totalRegistrosEl  = () => document.getElementById('totalRegistros');
const paginacaoDiv      = () => document.getElementById('paginacao');
const toastEl           = () => document.getElementById('toast');
const modalOverlay      = () => document.getElementById('modalOverlay');
const modoEdicaoAviso   = () => document.getElementById('modoEdicaoAviso');
const nomeEdicaoAviso   = () => document.getElementById('nomeEdicaoAviso');
const formCliente       = () => document.getElementById('formCliente');
const btnSalvar         = () => document.getElementById('btnSalvar');
const clienteIdInput    = () => document.getElementById('clienteId');

const CAMPOS_OBRIG = [
  ['tipoPessoa',   'erTipo'],
  ['nomeCliente',  'erNome'],
  ['documento',    'erDoc'],
  ['telCliente',   'erTel'],
  ['emailCliente', 'erEmail'],
  ['cep',          'erCep'],
  ['rua',          'erRua'],
  ['cidade',       'erCidade'],
];

document.addEventListener('DOMContentLoaded', () => {
  carregarLista();
  vincularEventos();
});

function vincularEventos() {
  /* Pesquisa */
  document.getElementById('btnPesquisar')
    .addEventListener('click', () => { paginaAtual = 1; carregarLista(); });
  campoPesquisa()
    .addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { paginaAtual = 1; carregarLista(); }
    });

  formCliente().addEventListener('submit', salvarCliente);
  document.getElementById('btnCancelar')
    .addEventListener('click', limparFormulario);

  document.getElementById('btnCancelaModal')
    .addEventListener('click', fecharModal);
  document.getElementById('btnConfirmaExclusao')
    .addEventListener('click', confirmarExclusao);

  document.getElementById('limiteCredito')
    .addEventListener('input', sugerirCategoria);

  document.getElementById('tipoPessoa')
    .addEventListener('change', atualizarMascaraDoc);

  CAMPOS_OBRIG.forEach(([idCampo, idErro]) => {
    ['input', 'change'].forEach((ev) => {
      document.getElementById(idCampo).addEventListener(ev, () => {
        document.getElementById(idCampo).classList.remove('error');
        document.getElementById(idErro).classList.remove('show');
      });
    });
  });
}

async function carregarLista() {
  const btn = document.getElementById('btnPesquisar');
  btn.disabled    = true;
  btn.textContent = 'Pesquisando…';

  /* Exibe spinner */
  resultadoDiv().style.display = 'block';
  tabelaBody().innerHTML =
    `<tr class="loading-row"><td colspan="6">⏳ Carregando…</td></tr>`;

  const search = campoPesquisa().value.trim();
  const params = new URLSearchParams({
    action: 'listar',
    search,
    pagina: paginaAtual,
    por_pagina: POR_PAG,
  });

  try {
    const res  = await fetch(`${API_URL}?${params}`);
    const json = await res.json();

    if (!json.ok) throw new Error(json.mensagem || 'Erro desconhecido');

    renderizarTabela(json.data);
    renderizarPaginacao(json.total, json.pagina, json.por_pagina);
    totalRegistrosEl().textContent = json.total;
  } catch (err) {
    toast('Erro ao carregar clientes: ' + err.message, 'err');
    tabelaBody().innerHTML =
      `<tr class="loading-row"><td colspan="6">Falha ao carregar dados.</td></tr>`;
  } finally {
    btn.disabled    = false;
    btn.textContent = '🔍 Pesquisar';
  }
}

function renderizarTabela(clientes) {
  tabelaBody().innerHTML = '';
  semResultadosP().style.display = 'none';

  if (!clientes || clientes.length === 0) {
    semResultadosP().style.display = 'block';
    return;
  }

  clientes.forEach((c, i) => {
    const tr = document.createElement('tr');
    tr.style.background = i % 2 === 0 ? '#f9f9f9' : 'white';

    const nome = c.nome_completo || c.nome || '';
    tr.innerHTML = `
      <td style="padding:8px 12px;border-bottom:1px solid #eee;">${c.id}</td>
      <td style="padding:8px 12px;border-bottom:1px solid #eee;">${escHtml(nome)}</td>
      <td style="padding:8px 12px;border-bottom:1px solid #eee;">${escHtml(c.documento || '')}</td>
      <td style="padding:8px 12px;border-bottom:1px solid #eee;">${escHtml(c.categoria || '')}</td>
      <td style="padding:8px 12px;border-bottom:1px solid #eee;">${escHtml(c.email || '')}</td>
      <td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;">
        <button class="btn-edit"   onclick="editarCliente(${c.id})">✏️ Editar</button>
        <button class="btn-delete" onclick="abrirModalExclusao(${c.id}, '${escAtr(nome)}')">🗑️ Excluir</button>
      </td>`;
    tabelaBody().appendChild(tr);
  });
}

function renderizarPaginacao(total, pagina, porPagina) {
  totalPaginas = Math.ceil(total / porPagina) || 1;
  paginacaoDiv().innerHTML = '';

  if (totalPaginas <= 1) return;

  const criar = (label, pg, ativo) => {
    const b = document.createElement('button');
    b.className   = 'pg-btn' + (ativo ? ' ativo' : '');
    b.textContent = label;
    b.disabled    = ativo;
    b.addEventListener('click', () => { paginaAtual = pg; carregarLista(); });
    return b;
  };

  if (pagina > 1)         paginacaoDiv().appendChild(criar('‹', pagina - 1));
  for (let p = 1; p <= totalPaginas; p++) {
    paginacaoDiv().appendChild(criar(p, p, p === pagina));
  }
  if (pagina < totalPaginas) paginacaoDiv().appendChild(criar('›', pagina + 1));
}

async function salvarCliente(e) {
  e.preventDefault();
  if (!validarFormulario()) return;

  const id     = clienteIdInput().value;
  const method = id ? 'PUT' : 'POST';

  const payload = coletarDadosFormulario();
  if (id) payload.id = id;

  btnSalvar().disabled    = true;
  btnSalvar().textContent = '⏳ Salvando…';

  try {
    const res  = await fetch(API_URL, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();

    if (!json.ok) throw new Error(json.mensagem || 'Erro ao salvar');

    toast(id ? 'Cliente atualizado com sucesso!' : 'Cliente cadastrado com sucesso!', 'ok');
    limparFormulario();
    carregarLista();
  } catch (err) {
    toast('Erro: ' + err.message, 'err');
  } finally {
    btnSalvar().disabled    = false;
    btnSalvar().textContent = '💾 Salvar Cliente';
  }
}

async function editarCliente(id) {
  try {
    const res  = await fetch(`${API_URL}?action=buscar&id=${id}`);
    const json = await res.json();

    if (!json.ok || !json.data) throw new Error(json.mensagem || 'Registro não encontrado');

    preencherFormulario(json.data);
  } catch (err) {
    toast('Erro ao carregar cliente: ' + err.message, 'err');
  }
}

function abrirModalExclusao(id, nome) {
  idParaExcluir = id;
  document.getElementById('modalMensagem').textContent =
    `Deseja realmente excluir o cliente "${nome}"? Esta ação não pode ser desfeita.`;
  modalOverlay().classList.add('show');
}

function fecharModal() {
  idParaExcluir = null;
  modalOverlay().classList.remove('show');
}

async function confirmarExclusao() {
  if (!idParaExcluir) return;
  fecharModal();

  try {
    const res  = await fetch(API_URL, {
      method:  'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id: idParaExcluir }),
    });
    const json = await res.json();

    if (!json.ok) throw new Error(json.mensagem || 'Erro ao excluir');

    toast('Cliente excluído com sucesso!', 'ok');
    carregarLista();
  } catch (err) {
    toast('Erro: ' + err.message, 'err');
  }
}

function coletarDadosFormulario() {
  return {
    tipo_pessoa:    document.getElementById('tipoPessoa').value,
    nome_completo:  document.getElementById('nomeCliente').value.trim(),
    documento:      document.getElementById('documento').value.trim(),
    data_nasc:      document.getElementById('dataNasc').value || null,
    limite_credito: document.getElementById('limiteCredito').value || null,
    categoria:      document.getElementById('categoriaCliente').value || null,
    telefone:       document.getElementById('telCliente').value.trim(),
    celular:        document.getElementById('celCliente').value.trim() || null,
    email:          document.getElementById('emailCliente').value.trim(),
    cep:            document.getElementById('cep').value.trim(),
    logradouro:     document.getElementById('rua').value.trim(),
    numero:         document.getElementById('numero').value.trim() || null,
    complemento:    document.getElementById('complemento').value.trim() || null,
    bairro:         document.getElementById('bairro').value.trim() || null,
    cidade:         document.getElementById('cidade').value.trim(),
    estado:         document.getElementById('estado').value || null,
  };
}

function preencherFormulario(c) {
  clienteIdInput().value = c.id;

  document.getElementById('tipoPessoa').value        = c.tipo_pessoa       || '';
  document.getElementById('nomeCliente').value       = c.nome_completo     || '';
  document.getElementById('documento').value         = c.documento         || '';
  document.getElementById('dataNasc').value          = c.data_nasc         || '';
  document.getElementById('limiteCredito').value     = c.limite_credito    || '';
  document.getElementById('categoriaCliente').value  = c.categoria         || '';
  document.getElementById('telCliente').value        = c.telefone          || '';
  document.getElementById('celCliente').value        = c.celular           || '';
  document.getElementById('emailCliente').value      = c.email             || '';
  document.getElementById('cep').value               = c.cep               || '';
  document.getElementById('rua').value               = c.logradouro        || '';
  document.getElementById('numero').value            = c.numero            || '';
  document.getElementById('complemento').value       = c.complemento       || '';
  document.getElementById('bairro').value            = c.bairro            || '';
  document.getElementById('cidade').value            = c.cidade            || '';
  document.getElementById('estado').value            = c.estado            || '';

  atualizarMascaraDoc();
  modoEdicaoAviso().classList.add('show');
  nomeEdicaoAviso().textContent = c.nome_completo || `#${c.id}`;
  btnSalvar().textContent = '💾 Atualizar Cliente';

  document.getElementById('painelFormulario')
    .scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function limparFormulario() {
  clienteIdInput().value = '';
  formCliente().reset();
  modoEdicaoAviso().classList.remove('show');
  btnSalvar().textContent = '💾 Salvar Cliente';
  CAMPOS_OBRIG.forEach(([idCampo, idErro]) => {
    document.getElementById(idCampo).classList.remove('error');
    document.getElementById(idErro).classList.remove('show');
  });
}

function abrirFormularioNovo() {
  limparFormulario();
  document.getElementById('painelFormulario')
    .scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function validarFormulario() {
  let ok = true;

  CAMPOS_OBRIG.forEach(([idCampo, idErro]) => {
    const el = document.getElementById(idCampo);
    if (!el.value.trim()) {
      el.classList.add('error');
      document.getElementById(idErro).classList.add('show');
      ok = false;
    }
  });

  const email = document.getElementById('emailCliente').value;
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    document.getElementById('emailCliente').classList.add('error');
    document.getElementById('erEmail').classList.add('show');
    ok = false;
  }

  if (!ok) toast('Preencha todos os campos obrigatórios.', 'err');
  return ok;
}

function sugerirCategoria() {
  const val = parseFloat(document.getElementById('limiteCredito').value) || 0;
  const sel = document.getElementById('categoriaCliente');
  const box = document.getElementById('infoBox');

  const mapa = [
    [50000, 'Platinum', '⭐ Categoria Platinum sugerida (limite ≥ R$ 50.000).'],
    [20000, 'Ouro',     '🥇 Categoria Ouro sugerida (R$ 20.000 – R$ 49.999).'],
    [ 5000, 'Prata',    '🥈 Categoria Prata sugerida (R$ 5.000 – R$ 19.999).'],
    [    0, 'Bronze',    null],
  ];

  for (const [min, cat, msg] of mapa) {
    if (val >= min) {
      sel.value     = cat;
      box.textContent = msg || '';
      box.className = msg ? 'info-box show' : 'info-box';
      break;
    }
  }
}

function atualizarMascaraDoc() {
  const tipo  = document.getElementById('tipoPessoa').value;
  const input = document.getElementById('documento');
  const label = document.getElementById('labelDoc');
  if (tipo === 'PF') {
    input.placeholder = '000.000.000-00';
    input.maxLength   = 14;
    label.innerHTML   = 'CPF <span class="req">*</span>';
  } else if (tipo === 'PJ') {
    input.placeholder = '00.000.000/0001-00';
    input.maxLength   = 18;
    label.innerHTML   = 'CNPJ <span class="req">*</span>';
  }
  input.value = '';
}

function toast(msg, tipo) {
  const t       = toastEl();
  t.textContent = msg;
  t.className   = `toast show ${tipo}`;
  setTimeout(() => { t.className = 'toast'; }, 3500);
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escAtr(str) {
  return String(str).replace(/'/g, "\\'");
}
