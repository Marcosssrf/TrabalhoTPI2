/**
 * auth.js — incluído em TODAS as páginas protegidas
 * Verifica sessão no servidor; redireciona para login se não autenticado.
 * Injeta nome do usuário e botão Sair no header.
 */
(async function () {
  'use strict';

  // Caminho da API relativo à página atual
  // Detecta se estamos em /pages/ ou na raiz
  const inPages = window.location.pathname.includes('/pages/');
  const API_AUTH = inPages ? 'auth_api.php' : 'pages/auth_api.php';
  const LOGIN_URL = inPages ? '../login.html' : 'login.html';

  async function checarSessao() {
    try {
      const res  = await fetch(API_AUTH + '?action=check');
      const json = await res.json();
      if (!json.ok) redirecionar();
      else injetarUI(json.data);
    } catch {
      redirecionar();
    }
  }

  function redirecionar() {
    sessionStorage.setItem('redirectAfterLogin', window.location.href);
    window.location.href = LOGIN_URL;
  }

  function injetarUI(usuario) {
    // Aguarda o DOM estar pronto
    const montar = () => {
      const nav = document.querySelector('header nav');
      if (!nav) return;

      // Cria área do usuário na direita do header
      let area = document.getElementById('auth-user-area');
      if (!area) {
        area = document.createElement('div');
        area.id = 'auth-user-area';
        area.style.cssText =
          'display:flex;align-items:center;gap:10px;font-size:.85rem;color:#cde;';
        nav.after(area);
      }

      const adminBadge = usuario.admin
        ? '<span style="background:#e67e22;color:#fff;border-radius:3px;padding:1px 6px;font-size:.75rem;margin-right:4px;">ADMIN</span>'
        : '';

      area.innerHTML = `
        ${adminBadge}
        <span>👤 ${escHtml(usuario.username)}</span>
        <button id="btn-logout-global"
          style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:4px;
                 padding:4px 10px;cursor:pointer;font-size:.82rem;">
          Sair
        </button>`;

      document.getElementById('btn-logout-global').addEventListener('click', async () => {
        await fetch(API_AUTH + '?action=logout', { method: 'POST' });
        window.location.href = LOGIN_URL;
      });

      // Guarda dados do usuário globalmente para as páginas usarem
      window.usuarioLogado = usuario;
      window.dispatchEvent(new CustomEvent('auth:pronto', { detail: usuario }));
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', montar);
    } else {
      montar();
    }
  }

  function escHtml(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  checarSessao();
})();
