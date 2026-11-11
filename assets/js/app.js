/* =========================================================
   Painel da aplicação (SPA simples com navegação por #hash)
   ========================================================= */
'use strict';

(() => {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const { esc, fmt, dataBR, hoje, nomeMes, ultimoDiaMes, toast } = UI;
  const campo = (form, nome) => form.elements.namedItem(nome);

  const TIPOS_CONTA = { carteira: 'Carteira', corrente: 'Conta corrente', poupanca: 'Poupança' };
  const VIEWS = ['painel', 'lancamentos', 'contas', 'categorias', 'relatorios'];

  const estado = {
    usuario: null,
    contas: [],
    saldoTotal: 0,
    categorias: [],
    lancamentos: new Map(), // cache para edição
    view: 'painel',
  };

  /* ---------------- Utilitários ---------------- */

  function tratarErro(e) {
    if (e && e.status === 401) {
      window.location.replace('index.html');
      return;
    }
    toast(e && e.message ? e.message : 'Erro inesperado.', 'erro');
    console.error(e);
  }

  const classeValor = (v) => (Number(v) < 0 ? 'neg' : '');

  async function carregarCadastros() {
    const [c, cat] = await Promise.all([Api.get('conta/listar'), Api.get('categoria/listar')]);
    estado.contas = c.contas;
    estado.saldoTotal = c.saldo_total;
    estado.categorias = cat.categorias;
    preencherFiltros();
  }

  function opcoesContas(vazio) {
    const ops = estado.contas.map((c) => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
    return (vazio ? `<option value="">${esc(vazio)}</option>` : '') + ops;
  }

  function opcoesCategorias(tipo, vazio) {
    const grupo = (t, rotulo) => {
      const itens = estado.categorias.filter((c) => c.tipo === t)
        .map((c) => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
      return tipo ? itens : `<optgroup label="${rotulo}">${itens}</optgroup>`;
    };
    let html = vazio ? `<option value="">${esc(vazio)}</option>` : '';
    if (!tipo || tipo === 'R') html += grupo('R', 'Receitas');
    if (!tipo || tipo === 'D') html += grupo('D', 'Despesas');
    return html;
  }

  function preencherSelect(select, html) {
    const atual = select.value;
    select.innerHTML = html;
    if ([...select.options].some((o) => o.value === atual)) select.value = atual;
  }

  function preencherFiltros() {
    preencherSelect($('#f-conta'), opcoesContas('Todas'));
    preencherSelect($('#f-categoria'), opcoesCategorias(null, 'Todas'));
    preencherSelect($('#r-categoria'), opcoesCategorias(null, 'Todas as categorias'));
  }

  function tabelaLancamentos(lista, { acoes = true, totais = false } = {}) {
    if (!lista.length) return '<p class="vazio">Nenhum lançamento encontrado.</p>';
    lista.forEach((l) => estado.lancamentos.set(String(l.id), l));
    let r = 0, d = 0;
    const linhas = lista.map((l) => {
      const valor = Number(l.valor);
      if (l.tipo === 'R') r += valor; else d += valor;
      return `<tr>
        <td>${dataBR(l.data)}</td>
        <td class="desc">${esc(l.descricao || '—')}</td>
        <td><span class="tag tag-${l.tipo}">${esc(l.categoria_nome)}</span></td>
        <td>${esc(l.conta_nome)}</td>
        <td class="num ${l.tipo === 'R' ? 'pos' : 'neg'}">${l.tipo === 'R' ? '+' : '−'} ${fmt(valor)}</td>
        ${acoes ? `<td class="num">
          <button type="button" class="btn-link" data-acao="editar-lanc" data-id="${l.id}">Editar</button>
          <button type="button" class="btn-link perigo" data-acao="excluir-lanc" data-id="${l.id}">Excluir</button>
        </td>` : ''}
      </tr>`;
    }).join('');
    const rodape = totais ? `<tfoot><tr>
        <td colspan="4">Saldo do período (receitas − despesas)</td>
        <td class="num ${r - d < 0 ? 'neg' : 'pos'}">${fmt(r - d)}</td>${acoes ? '<td></td>' : ''}
      </tr></tfoot>` : '';
    return `<table>
      <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Conta</th><th class="num">Valor</th>${acoes ? '<th></th>' : ''}</tr></thead>
      <tbody>${linhas}</tbody>${rodape}
    </table>`;
  }

  function barras(lista, classe = '') {
    if (!lista.length) return '<p class="vazio">Sem movimentações no período.</p>';
    const max = Math.max(...lista.map((i) => Number(i.total)));
    const soma = lista.reduce((s, i) => s + Number(i.total), 0);
    return lista.map((i) => {
      const pct = soma ? (Number(i.total) / soma) * 100 : 0;
      const largura = max ? (Number(i.total) / max) * 100 : 0;
      return `<div class="barra-linha">
        <span>${esc(i.nome)} <small class="muted">${pct.toFixed(1).replace('.', ',')}%</small></span>
        <span class="valor">${fmt(i.total)}</span>
        <div class="barra-trilho ${classe}"><span style="width:${largura.toFixed(1)}%"></span></div>
      </div>`;
    }).join('');
  }

  function confirmar(mensagem) {
    const dlg = $('#dlg-confirmar');
    $('#confirmar-msg').textContent = mensagem;
    dlg.returnValue = '';
    dlg.showModal();
    return new Promise((resolve) => {
      dlg.addEventListener('close', () => resolve(dlg.returnValue === 'sim'), { once: true });
    });
  }

  /* ---------------- Navegação ---------------- */

  const renderizadores = {
    painel: renderPainel,
    lancamentos: renderLancamentos,
    contas: renderContas,
    categorias: renderCategorias,
    relatorios: renderRelatorios,
  };

  async function navegar() {
    const view = location.hash.slice(1);
    estado.view = VIEWS.includes(view) ? view : 'painel';
    $$('.view').forEach((v) => v.classList.toggle('oculto', v.id !== `view-${estado.view}`));
    $$('.menu a').forEach((a) => a.classList.toggle('ativo', a.dataset.view === estado.view));
    await atualizarView();
  }

  async function atualizarView() {
    try {
      await renderizadores[estado.view]();
    } catch (e) {
      tratarErro(e);
    }
  }

  /* ---------------- Painel ---------------- */

  async function renderPainel() {
    const r = await Api.get('dashboard/resumo');
    $('#saudacao').textContent = estado.usuario.nome.split(' ')[0];
    $('#painel-mes').textContent = `Resumo de ${nomeMes(r.mes)}`;

    $('#stat-saldo').textContent = fmt(r.saldo_total);
    $('#stat-receitas').textContent = fmt(r.totais_mes.receitas);
    $('#stat-despesas').textContent = fmt(r.totais_mes.despesas);
    const res = $('#stat-resultado');
    res.textContent = fmt(r.totais_mes.saldo);
    res.className = r.totais_mes.saldo < 0 ? 'neg' : 'pos';

    $('#painel-contas').innerHTML = r.contas.length
      ? r.contas.map((c) => `<li>
          <div><strong>${esc(c.nome)}</strong><small>${TIPOS_CONTA[c.tipo] || c.tipo}</small></div>
          <span class="valor ${classeValor(c.saldo_atual)}">${fmt(c.saldo_atual)}</span>
        </li>`).join('')
      : '<li class="vazio">Nenhuma conta cadastrada. <a href="#contas">Criar conta</a></li>';

    $('#painel-categorias').innerHTML = barras(r.despesas_categoria);

    const maxEvo = Math.max(1, ...r.evolucao.flatMap((m) => [m.receitas, m.despesas]));
    $('#painel-evolucao').innerHTML = r.evolucao.map((m) => `
      <div class="evo-mes" title="${nomeMes(m.mes)} — Receitas ${fmt(m.receitas)} / Despesas ${fmt(m.despesas)}">
        <div class="evo-colunas">
          <span class="r" style="height:${(m.receitas / maxEvo) * 100}%"></span>
          <span class="d" style="height:${(m.despesas / maxEvo) * 100}%"></span>
        </div>
        <small>${nomeMes(m.mes, true)}</small>
      </div>`).join('');

    $('#painel-ultimos').innerHTML = tabelaLancamentos(r.ultimos, { acoes: false });
  }

  /* ---------------- Lançamentos ---------------- */

  function definirFiltroPadrao() {
    const mes = hoje().slice(0, 7);
    if (!$('#f-inicio').value) $('#f-inicio').value = `${mes}-01`;
    if (!$('#f-fim').value) $('#f-fim').value = ultimoDiaMes(mes);
  }

  async function renderLancamentos() {
    definirFiltroPadrao();
    const params = Object.fromEntries(new FormData($('#form-filtros')));
    if (params.inicio && params.fim && params.inicio > params.fim) {
      toast('A data inicial deve ser anterior à final.', 'erro');
      return;
    }
    const r = await Api.get('lancamento/listar', params);
    const t = r.totais;
    $('#lanc-resumo').innerHTML = `
      <span>${t.quantidade} lançamento(s)</span>
      <span>Receitas: <strong class="pos">${fmt(t.receitas)}</strong></span>
      <span>Despesas: <strong class="neg">${fmt(t.despesas)}</strong></span>
      <span>Saldo: <strong class="${t.saldo < 0 ? 'neg' : 'pos'}">${fmt(t.saldo)}</strong></span>`;
    $('#lanc-tabela').innerHTML = tabelaLancamentos(r.lancamentos);
  }

  function definirTipoLancamento(tipo) {
    const form = $('#form-lancamento');
    campo(form, 'tipo').value = tipo;
    $$('#lanc-tipo button').forEach((b) => b.classList.toggle('ativo', b.dataset.tipo === tipo));
    preencherSelect(campo(form, 'categoria_id'), opcoesCategorias(tipo, 'Selecione...'));
    const id = campo(form, 'id').value;
    $('#lanc-titulo').textContent = `${id ? 'Editar' : 'Nova'} ${tipo === 'R' ? 'receita' : 'despesa'}`;
  }

  function abrirLancamento(tipo, lanc = null) {
    if (!estado.contas.length) {
      toast('Cadastre uma conta antes de registrar lançamentos.', 'erro');
      location.hash = '#contas';
      return;
    }
    const form = $('#form-lancamento');
    form.reset();
    UI.limparErros(form);
    campo(form, 'id').value = lanc ? lanc.id : '';
    preencherSelect(campo(form, 'conta_id'), opcoesContas('Selecione...'));
    definirTipoLancamento(lanc ? lanc.tipo : tipo);

    campo(form, 'valor').value = lanc ? Number(lanc.valor).toFixed(2) : '';
    campo(form, 'data').value = lanc ? lanc.data : hoje();
    campo(form, 'conta_id').value = lanc ? lanc.conta_id : (estado.contas.length === 1 ? estado.contas[0].id : '');
    campo(form, 'categoria_id').value = lanc ? lanc.categoria_id : '';
    campo(form, 'descricao').value = lanc ? (lanc.descricao || '') : '';

    $('#dlg-lancamento').showModal();
    campo(form, 'valor').focus();
  }

  async function salvarLancamento(ev) {
    ev.preventDefault();
    const form = ev.target;
    const dados = Object.fromEntries(new FormData(form));

    // Validação no cliente (o servidor valida novamente)
    const erros = {};
    if (!(Number(dados.valor) > 0)) erros.valor = 'Informe um valor maior que zero.';
    if (!dados.data) erros.data = 'Informe a data.';
    if (!dados.conta_id) erros.conta_id = 'Selecione uma conta.';
    if (!dados.categoria_id) erros.categoria_id = 'Selecione uma categoria.';
    if (Object.keys(erros).length) {
      UI.mostrarErrosCampos(form, erros);
      return;
    }

    try {
      const r = await UI.comBotao(form, () => Api.post('lancamento/salvar', dados));
      $('#dlg-lancamento').close();
      toast(`${r.mensagem} Saldo de "${r.conta.nome}": ${fmt(r.conta.saldo_atual)}`);
      await carregarCadastros();
      await atualizarView();
    } catch (e) {
      UI.mostrarErrosCampos(form, e.campos);
      tratarErro(e);
    }
  }

  async function excluirLancamento(id) {
    const l = estado.lancamentos.get(String(id));
    const desc = l ? `${l.descricao || l.categoria_nome} (${fmt(l.valor)})` : 'este lançamento';
    if (!(await confirmar(`Excluir ${desc}? O saldo da conta será estornado.`))) return;
    try {
      const r = await Api.post('lancamento/excluir', { id });
      toast(`${r.mensagem} Saldo de "${r.conta.nome}": ${fmt(r.conta.saldo_atual)}`);
      await carregarCadastros();
      await atualizarView();
    } catch (e) {
      tratarErro(e);
    }
  }

  /* ---------------- Contas ---------------- */

  async function renderContas() {
    await carregarCadastros();
    $('#contas-total').textContent = fmt(estado.saldoTotal);
    $('#contas-total').className = classeValor(estado.saldoTotal);
    $('#grade-contas').innerHTML = estado.contas.length
      ? estado.contas.map((c) => `
        <article class="card conta-card">
          <header>
            <div><h2>${esc(c.nome)}</h2></div>
            <span class="tipo-conta">${TIPOS_CONTA[c.tipo] || c.tipo}</span>
          </header>
          <div>
            <small class="muted">Saldo atual</small>
            <div class="saldo ${classeValor(c.saldo_atual)}">${fmt(c.saldo_atual)}</div>
          </div>
          <footer>
            <span>Inicial: ${fmt(c.saldo_inicial)} · ${c.qtd_lancamentos} lanç.</span>
            <span>
              <button type="button" class="btn-link" data-acao="editar-conta" data-id="${c.id}">Editar</button>
              <button type="button" class="btn-link perigo" data-acao="excluir-conta" data-id="${c.id}">Excluir</button>
            </span>
          </footer>
        </article>`).join('')
      : '<div class="card vazio">Nenhuma conta cadastrada ainda.</div>';
  }

  function abrirConta(conta = null) {
    const form = $('#form-conta');
    form.reset();
    UI.limparErros(form);
    campo(form, 'id').value = conta ? conta.id : '';
    campo(form, 'nome').value = conta ? conta.nome : '';
    campo(form, 'tipo').value = conta ? conta.tipo : 'carteira';
    campo(form, 'saldo_inicial').value = conta ? Number(conta.saldo_inicial).toFixed(2) : '0.00';
    $('#conta-titulo').textContent = conta ? 'Editar conta' : 'Nova conta';
    $('#conta-dica').textContent = conta
      ? 'Alterar o saldo inicial ajusta o saldo atual pela diferença.'
      : 'O saldo atual começa igual ao saldo inicial.';
    $('#dlg-conta').showModal();
    campo(form, 'nome').focus();
  }

  async function salvarConta(ev) {
    ev.preventDefault();
    const form = ev.target;
    const dados = Object.fromEntries(new FormData(form));
    if (!dados.nome.trim()) {
      UI.mostrarErrosCampos(form, { nome: 'Informe o nome da conta.' });
      return;
    }
    try {
      const r = await UI.comBotao(form, () => Api.post('conta/salvar', dados));
      $('#dlg-conta').close();
      toast(r.mensagem);
      await carregarCadastros();
      await atualizarView();
    } catch (e) {
      UI.mostrarErrosCampos(form, e.campos);
      tratarErro(e);
    }
  }

  async function excluirConta(id) {
    const c = estado.contas.find((x) => String(x.id) === String(id));
    if (!(await confirmar(`Excluir a conta "${c ? c.nome : ''}"?`))) return;
    try {
      const r = await Api.post('conta/excluir', { id });
      toast(r.mensagem);
      await atualizarView();
    } catch (e) {
      tratarErro(e);
    }
  }

  /* ---------------- Categorias ---------------- */

  async function renderCategorias() {
    await carregarCadastros();
    ['R', 'D'].forEach((tipo) => {
      const lista = estado.categorias.filter((c) => c.tipo === tipo);
      $(`#cat-${tipo}`).innerHTML = lista.length
        ? lista.map((c) => `<li>
            <span>${esc(c.nome)}<small>${c.qtd_lancamentos} lanç.</small></span>
            <span>
              <button type="button" class="btn-link" data-acao="editar-cat" data-id="${c.id}">Editar</button>
              <button type="button" class="btn-link perigo" data-acao="excluir-cat" data-id="${c.id}">Excluir</button>
            </span>
          </li>`).join('')
        : '<li class="vazio">Nenhuma categoria.</li>';
    });
  }

  function abrirCategoria(cat = null) {
    const form = $('#form-categoria');
    form.reset();
    UI.limparErros(form);
    campo(form, 'id').value = cat ? cat.id : '';
    campo(form, 'nome').value = cat ? cat.nome : '';
    campo(form, 'tipo').value = cat ? cat.tipo : 'D';
    $('#cat-titulo').textContent = cat ? 'Editar categoria' : 'Nova categoria';
    $('#dlg-categoria').showModal();
    campo(form, 'nome').focus();
  }

  async function salvarCategoria(ev) {
    ev.preventDefault();
    const form = ev.target;
    const dados = Object.fromEntries(new FormData(form));
    if (!dados.nome.trim()) {
      UI.mostrarErrosCampos(form, { nome: 'Informe o nome da categoria.' });
      return;
    }
    try {
      const r = await UI.comBotao(form, () => Api.post('categoria/salvar', dados));
      $('#dlg-categoria').close();
      toast(r.mensagem);
      await carregarCadastros();
      await atualizarView();
    } catch (e) {
      UI.mostrarErrosCampos(form, e.campos);
      tratarErro(e);
    }
  }

  async function excluirCategoria(id) {
    const c = estado.categorias.find((x) => String(x.id) === String(id));
    if (!(await confirmar(`Excluir a categoria "${c ? c.nome : ''}"?`))) return;
    try {
      const r = await Api.post('categoria/excluir', { id });
      toast(r.mensagem);
      await atualizarView();
    } catch (e) {
      tratarErro(e);
    }
  }

  /* ---------------- Relatórios ---------------- */

  function modoRelatorio() {
    return campo($('#form-relatorio'), 'modo').value;
  }

  function alternarModoRelatorio() {
    const modo = modoRelatorio();
    $$('#form-relatorio [data-modo]').forEach((el) => el.classList.toggle('oculto', el.dataset.modo !== modo));
    if (modo === 'periodo') {
      const mes = $('#r-mes').value || hoje().slice(0, 7);
      if (!$('#r-inicio').value) $('#r-inicio').value = `${mes}-01`;
      if (!$('#r-fim').value) $('#r-fim').value = ultimoDiaMes(mes);
    }
  }

  async function renderRelatorios() {
    if (!$('#r-mes').value) $('#r-mes').value = hoje().slice(0, 7);
    const form = $('#form-relatorio');
    const params = { categoria_id: campo(form, 'categoria_id').value };
    if (modoRelatorio() === 'mes') {
      params.mes = $('#r-mes').value;
    } else {
      params.inicio = $('#r-inicio').value;
      params.fim = $('#r-fim').value;
      if (!params.inicio || !params.fim) {
        toast('Informe as datas de início e fim.', 'erro');
        return;
      }
    }

    const r = await Api.get('relatorio/mensal', params);
    const periodo = r.mes
      ? `Relatório de ${nomeMes(r.mes)}`
      : `Período de ${dataBR(r.inicio)} a ${dataBR(r.fim)}`;
    const filtroCat = r.categoria ? ` · Categoria: ${esc(r.categoria.nome)}` : '';
    const receitas = r.por_categoria.filter((c) => c.tipo === 'R');
    const despesas = r.por_categoria.filter((c) => c.tipo === 'D');

    const tabelaCat = (lista, total, classe) => {
      if (!lista.length) return '<p class="vazio">Sem movimentações.</p>';
      return `<table>
        <thead><tr><th>Categoria</th><th class="num">Qtd.</th><th class="num">Total</th><th class="num">%</th></tr></thead>
        <tbody>${lista.map((c) => `<tr>
          <td>${esc(c.nome)}</td><td class="num">${c.quantidade}</td>
          <td class="num ${classe}">${fmt(c.total)}</td>
          <td class="num">${total ? ((c.total / total) * 100).toFixed(1).replace('.', ',') : '0,0'}%</td>
        </tr>`).join('')}</tbody>
        <tfoot><tr><td>Total</td><td class="num">${lista.reduce((s, c) => s + c.quantidade, 0)}</td><td class="num ${classe}">${fmt(total)}</td><td class="num">100%</td></tr></tfoot>
      </table>`;
    };

    $('#relatorio-saida').innerHTML = `
      <h2 class="titulo-relatorio">${esc(periodo)}${filtroCat}</h2>
      <div class="cards cards-3">
        <div class="card stat"><span class="stat-label">Receitas</span><strong class="pos">${fmt(r.totais.receitas)}</strong></div>
        <div class="card stat"><span class="stat-label">Despesas</span><strong class="neg">${fmt(r.totais.despesas)}</strong></div>
        <div class="card stat"><span class="stat-label">Saldo do período</span><strong class="${r.totais.saldo < 0 ? 'neg' : 'pos'}">${fmt(r.totais.saldo)}</strong></div>
      </div>
      <div class="grade-2">
        <div class="card"><h2>Despesas por categoria</h2><div class="barras" style="margin-bottom:16px">${barras(despesas)}</div><div class="tabela-wrap">${tabelaCat(despesas, r.totais.despesas, 'neg')}</div></div>
        <div class="card"><h2>Receitas por categoria</h2><div class="barras" style="margin-bottom:16px">${barras(receitas, 'receita')}</div><div class="tabela-wrap">${tabelaCat(receitas, r.totais.receitas, 'pos')}</div></div>
      </div>
      <div class="card"><h2>Lançamentos do período</h2><div class="tabela-wrap">${tabelaLancamentos(r.lancamentos, { acoes: false, totais: true })}</div></div>`;
  }

  /* ---------------- Eventos ---------------- */

  function registrarEventos() {
    window.addEventListener('hashchange', navegar);

    document.addEventListener('click', (ev) => {
      const novo = ev.target.closest('[data-novo]');
      if (novo) return abrirLancamento(novo.dataset.novo);

      const fechar = ev.target.closest('[data-fechar]');
      if (fechar) return fechar.closest('dialog').close();

      const btn = ev.target.closest('[data-acao]');
      if (!btn) return;
      const id = btn.dataset.id;
      switch (btn.dataset.acao) {
        case 'editar-lanc': return abrirLancamento(null, estado.lancamentos.get(String(id)));
        case 'excluir-lanc': return excluirLancamento(id);
        case 'editar-conta': return abrirConta(estado.contas.find((c) => String(c.id) === id));
        case 'excluir-conta': return excluirConta(id);
        case 'editar-cat': return abrirCategoria(estado.categorias.find((c) => String(c.id) === id));
        case 'excluir-cat': return excluirCategoria(id);
        default: return undefined;
      }
    });

    $$('#lanc-tipo button').forEach((b) => b.addEventListener('click', () => definirTipoLancamento(b.dataset.tipo)));

    $('#form-lancamento').addEventListener('submit', salvarLancamento);
    $('#form-conta').addEventListener('submit', salvarConta);
    $('#form-categoria').addEventListener('submit', salvarCategoria);

    $('#btn-nova-conta').addEventListener('click', () => abrirConta());
    $('#btn-nova-categoria').addEventListener('click', () => abrirCategoria());

    $('#form-filtros').addEventListener('submit', (ev) => { ev.preventDefault(); atualizarView(); });

    $('#form-relatorio').addEventListener('submit', (ev) => { ev.preventDefault(); atualizarView(); });
    $$('#form-relatorio [name="modo"]').forEach((r) => r.addEventListener('change', alternarModoRelatorio));
    $('#btn-imprimir').addEventListener('click', () => window.print());

    $('#btn-sair').addEventListener('click', async () => {
      try {
        const r = await Api.post('auth/logout');
        window.location.replace(r.redirecionar);
      } catch (e) {
        window.location.replace('index.html');
      }
    });
  }

  /* ---------------- Inicialização ---------------- */

  async function iniciar() {
    try {
      const s = await Api.sessao();
      if (!s.usuario) {
        window.location.replace('index.html');
        return;
      }
      estado.usuario = s.usuario;
      $('#usuario-nome').textContent = s.usuario.nome;
      $('#usuario-email').textContent = s.usuario.email;
      await carregarCadastros();
      registrarEventos();
      await navegar();
    } catch (e) {
      tratarErro(e);
    }
  }

  document.addEventListener('DOMContentLoaded', iniciar);
})();
