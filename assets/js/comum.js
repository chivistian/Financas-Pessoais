/* =========================================================
   Utilitários compartilhados: cliente da API, toasts, formatação
   ========================================================= */
'use strict';

class ErroApi extends Error {
  constructor(mensagem, status, campos) {
    super(mensagem);
    this.status = status;
    this.campos = campos || null;
  }
}

const Api = (() => {
  let csrf = null;

  async function chamar(metodo, rota, { dados, params } = {}) {
    const url = new URL('api.php', window.location.href);
    url.searchParams.set('r', rota);
    if (params) {
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, v);
      });
    }

    const opcoes = { method: metodo, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
    if (metodo === 'POST') {
      if (!csrf) await sessao();
      opcoes.headers['Content-Type'] = 'application/json';
      opcoes.headers['X-CSRF-Token'] = csrf;
      opcoes.body = JSON.stringify(dados || {});
    }

    let resp, json;
    try {
      resp = await fetch(url, opcoes);
    } catch (e) {
      throw new ErroApi('Não foi possível conectar ao servidor.', 0);
    }
    try {
      json = await resp.json();
    } catch (e) {
      throw new ErroApi('Resposta inválida do servidor. Verifique se o PHP está em execução.', resp.status);
    }
    if (!resp.ok || json.ok === false) {
      throw new ErroApi(json.erro || 'Erro inesperado.', resp.status, json.campos);
    }
    return json;
  }

  async function sessao() {
    const r = await chamar('GET', 'auth/sessao');
    csrf = r.csrf;
    return r;
  }

  return {
    sessao,
    get: (rota, params) => chamar('GET', rota, { params }),
    post: (rota, dados) => chamar('POST', rota, { dados }),
  };
})();

const UI = (() => {
  const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  function toast(msg, tipo = 'sucesso') {
    let box = document.getElementById('toasts');
    if (!box) {
      box = document.createElement('div');
      box.id = 'toasts';
      document.body.appendChild(box);
    }
    const el = document.createElement('div');
    el.className = `toast toast-${tipo}`;
    el.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
    el.textContent = msg;
    box.appendChild(el);
    setTimeout(() => {
      el.classList.add('saindo');
      setTimeout(() => el.remove(), 300);
    }, tipo === 'erro' ? 6000 : 4000);
  }

  /** Marca os campos com erro retornados pela API. */
  function mostrarErrosCampos(form, campos) {
    limparErros(form);
    if (!campos) return;
    Object.entries(campos).forEach(([nome, msg]) => {
      const input = form.elements.namedItem(nome);
      const campo = input && (input.closest ? input.closest('.campo') : null);
      if (!campo) return;
      campo.classList.add('invalido');
      const small = document.createElement('small');
      small.className = 'erro-campo';
      small.textContent = msg;
      campo.appendChild(small);
    });
  }

  function limparErros(form) {
    form.querySelectorAll('.campo.invalido').forEach((c) => c.classList.remove('invalido'));
    form.querySelectorAll('.erro-campo').forEach((e) => e.remove());
  }

  /** Executa uma ação desabilitando o botão de submit enquanto aguarda. */
  async function comBotao(form, acao) {
    const btn = form.querySelector('[type="submit"]');
    if (btn) btn.disabled = true;
    try {
      return await acao();
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));

  const fmt = (v) => moeda.format(Number(v) || 0);

  const dataBR = (iso) => {
    if (!iso) return '';
    const [a, m, d] = String(iso).slice(0, 10).split('-');
    return `${d}/${m}/${a}`;
  };

  const hoje = () => {
    const d = new Date();
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  };

  const nomeMes = (ym, curto = false) => {
    const [a, m] = ym.split('-').map(Number);
    const opcoes = curto ? { month: 'short' } : { month: 'long', year: 'numeric' };
    return new Date(a, m - 1, 1).toLocaleDateString('pt-BR', opcoes).replace('.', '');
  };

  const ultimoDiaMes = (ym) => {
    const [a, m] = ym.split('-').map(Number);
    return `${ym}-${String(new Date(a, m, 0).getDate()).padStart(2, '0')}`;
  };

  return { toast, mostrarErrosCampos, limparErros, comBotao, esc, fmt, dataBR, hoje, nomeMes, ultimoDiaMes };
})();
