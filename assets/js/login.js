/* Tela de login e cadastro */
'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  const formLogin = document.getElementById('form-login');
  const formCadastro = document.getElementById('form-cadastro');

  // Já está logado? Vai direto para o painel.
  try {
    const s = await Api.sessao();
    if (s.usuario) {
      window.location.replace('painel.html');
      return;
    }
  } catch (e) {
    UI.toast(e.message, 'erro');
  }

  // Abas
  document.querySelectorAll('[data-aba]').forEach((botao) => {
    botao.addEventListener('click', () => {
      document.querySelectorAll('[data-aba]').forEach((b) => b.classList.toggle('ativo', b === botao));
      const aba = botao.dataset.aba;
      formLogin.classList.toggle('oculto', aba !== 'login');
      formCadastro.classList.toggle('oculto', aba !== 'cadastro');
    });
  });

  document.getElementById('btn-demo').addEventListener('click', () => {
    formLogin.elements.namedItem('email').value = 'demo@financas.com';
    formLogin.elements.namedItem('senha').value = 'demo123';
  });

  // Fazer login
  formLogin.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    UI.limparErros(formLogin);
    const dados = Object.fromEntries(new FormData(formLogin));
    if (!dados.email || !dados.senha) {
      UI.toast('Informe e-mail e senha.', 'erro');
      return;
    }
    try {
      const r = await UI.comBotao(formLogin, () => Api.post('auth/login', dados));
      window.location.href = r.redirecionar;
    } catch (e) {
      UI.toast(e.message, 'erro');
    }
  });

  // Cadastrar-se
  formCadastro.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const dados = Object.fromEntries(new FormData(formCadastro));
    if (dados.senha !== dados.confirmar) {
      UI.mostrarErrosCampos(formCadastro, { confirmar: 'As senhas não conferem.' });
      return;
    }
    try {
      const r = await UI.comBotao(formCadastro, () => Api.post('auth/cadastro', dados));
      window.location.href = r.redirecionar;
    } catch (e) {
      UI.mostrarErrosCampos(formCadastro, e.campos);
      UI.toast(e.message, 'erro');
    }
  });
});
