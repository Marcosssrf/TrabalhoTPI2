const api = 'usuarios_api.php';
let logado = false;
let usuarioEditando = null;

function toast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + (ok ? 'ok' : 'err');
    setTimeout(() => { t.className = 'toast'; }, 2600);
}

function setLogado(estado, nome) {
    logado = estado;
    const s = document.getElementById('sessao');
    document.getElementById('btn_logout').style.display = estado ? '' : 'none';
    document.getElementById('btn_login').style.display = estado ? 'none' : '';
    document.getElementById('pin-modal').style.display = estado ? 'none' : '';
    document.getElementById('pin-locked').style.display = estado ? 'none' : '';
    if (estado) {
        s.textContent = 'Conectado: ' + nome;
        s.classList.add('on');
    } else {
        s.textContent = 'Não autenticado';
        s.classList.remove('on');
        document.getElementById('results').innerHTML = '<p class="locked">Faça login para pesquisar e gerenciar usuários.</p>';
    }
}

async function chamarApi(url, opcoes) {
    const res = await fetch(url, opcoes);
    let j = {};
    try { j = await res.json(); } catch (e) { j = { ok: false, mensagem: 'Resposta inválida do servidor.' }; }
    return j;
}

document.getElementById('btn_reg').onclick = async () => {
    const u = document.getElementById('reg_user').value.trim();
    const p = document.getElementById('reg_pass').value;
    const pin = document.getElementById('reg_pin').value.trim();
    if (!u || !p) { toast('Informe usuário e senha.', false); return; }
    if (p.length < 4) { toast('A senha deve ter ao menos 4 caracteres.', false); return; }

    const j = await chamarApi(api + '?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: u, password: p, pin: pin })
    });
    toast(j.mensagem, j.ok);
    if (j.ok) {
        document.getElementById('reg_user').value = '';
        document.getElementById('reg_pass').value = '';
        document.getElementById('reg_pin').value = '';
        if (logado) document.getElementById('btn_search').click();
    }
};

document.getElementById('btn_login').onclick = async () => {
    const u = document.getElementById('login_user').value.trim();
    const pin = document.getElementById('login_pin').value.trim();
    const p = document.getElementById('login_pass').value;
    if ((!u && !pin) || !p) { toast('Informe (usuário ou PIN) e senha.', false); return; }

    const j = await chamarApi(api + '?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: u, pin: pin, password: p })
    });
    toast(j.mensagem, j.ok);
    if (j.ok) {
        setLogado(true, (j.data && j.data.username) || u);
        document.getElementById('login_pass').value = '';
        document.getElementById('btn_search').click();
    }
};

document.getElementById('btn_logout').onclick = async () => {
    const j = await chamarApi(api + '?action=logout', { method: 'POST' });
    toast(j.mensagem, j.ok);
    setLogado(false);
};

document.getElementById('btn_search').onclick = async () => {
    const q = document.getElementById('search_q').value.trim();
    const url = api + (q ? '?search=' + encodeURIComponent(q) : '');
    const j = await chamarApi(url);
    const out = document.getElementById('results');

    if (!j.ok) {
        out.innerHTML = '<p class="locked">' + (j.mensagem || 'Erro.') + '</p>';
        if (/autoriz/i.test(j.mensagem || '')) setLogado(false);
        return;
    }

    if (!j.data || j.data.length === 0) {
        out.innerHTML = '<p class="empty">Nenhum usuário encontrado' + (q ? ' para "' + q + '"' : '') + '.</p>';
        return;
    }

    let html = '<table class="users"><thead><tr><th>ID</th><th>Usuário</th><th>PIN</th><th>Criado em</th><th>Ação</th></tr></thead><tbody>';
    j.data.forEach(u => {
        html += '<tr>'
            + '<td>' + u.id + '</td>'
            + '<td>' + escapeHtml(u.username) + '</td>'
            + '<td>' + (u.pin ? escapeHtml(u.pin) : '<span style="color:#999;">-</span>') + '</td>'
            + '<td>' + (u.criado_em || '') + '</td>'
            + '<td><button class="btn-submit" style="padding:4px 8px; font-size:.8rem; margin-right:4px;" onclick="abrirEditarPin(' + u.id + ', ' + "'" + (u.pin ? escapeHtml(u.pin) : '') + "'" + ')">PIN</button><button class="btn-del" data-id="' + u.id + '" data-user="' + escapeHtml(u.username) + '">Deletar</button></td>'
            + '</tr>';
    });
    html += '</tbody></table>';
    out.innerHTML = html;

    out.querySelectorAll('.btn-del').forEach(btn => {
        btn.onclick = async () => {
            const id = btn.getAttribute('data-id');
            const nome = btn.getAttribute('data-user');
            if (!confirm('Deseja realmente deletar o usuário "' + nome + '"?')) return;
            const jj = await chamarApi(api, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: Number(id) })
            });
            toast(jj.mensagem, jj.ok);
            if (jj.ok) document.getElementById('btn_search').click();
        };
    });
};

function abrirEditarPin(userId, pinAtual) {
    if (!logado) { toast('Faça login primeiro.', false); return; }
    usuarioEditando = userId;
    document.getElementById('novo_pin').value = pinAtual || '';
    document.getElementById('pin-info').textContent = 'Editando PIN do usuário ID ' + userId;
    document.getElementById('pin-modal').style.display = '';
}

document.getElementById('btn_salvar_pin').onclick = async () => {
    if (!usuarioEditando) return;
    const novoPin = document.getElementById('novo_pin').value.trim();
    const j = await chamarApi(api + '?action=atualizarpin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: usuarioEditando, pin: novoPin })
    });
    toast(j.mensagem, j.ok);
    if (j.ok) {
        document.getElementById('pin-modal').style.display = 'none';
        document.getElementById('btn_search').click();
    }
};

document.getElementById('btn_cancelar_pin').onclick = () => {
    document.getElementById('pin-modal').style.display = 'none';
};

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
