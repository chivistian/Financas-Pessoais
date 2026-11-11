# Sistema de Gestão de Finanças Pessoais

Aplicação web em **HTML + CSS + JavaScript** (front-end) e **PHP + MySQL** (back-end),
desenvolvida a partir dos diagramas de casos de uso, sequência e MER do projeto.

## Requisitos
- PHP 7.4 ou superior (recomendado 8.x) com as extensões `pdo_mysql` e `mbstring`
- MySQL 5.7+ ou MariaDB 10.3+
- A forma mais simples: **XAMPP** (https://www.apachefriends.org)

## Instalação (XAMPP)
1. Copie a pasta `financas-pessoais` para `C:\xampp\htdocs\`.
2. Abra o XAMPP Control Panel e inicie **Apache** e **MySQL**.
3. Se o seu MySQL tiver senha, ajuste `config/config.php`.
4. Acesse **http://localhost/financas-pessoais/install.php**
   - cria o banco `financas_pessoais`, as tabelas e o usuário de demonstração.
5. Acesse **http://localhost/financas-pessoais/** e faça login.

### Alternativa sem Apache (servidor embutido do PHP)
Com o MySQL rodando, dentro da pasta do projeto:
```
php -S localhost:8000
```
Depois acesse `http://localhost:8000/install.php` e em seguida `http://localhost:8000/`.

## Acesso de demonstração
| E-mail              | Senha     |
|---------------------|-----------|
| demo@financas.com   | demo123   |

O usuário demo vem com 3 contas (Carteira, Conta Corrente, Poupança), categorias padrão
e lançamentos dos últimos 4 meses. Para restaurar os dados de exemplo: `install.php?reset=1`.

## Estrutura
```
index.html            Login e cadastro
painel.html           Aplicação (painel, lançamentos, contas, categorias, relatórios)
api.php               Front controller da API (api.php?r=controle/acao)
install.php           Instalador do banco + dados de demonstração
assets/css/style.css
assets/js/comum.js    Cliente da API, toasts e formatação
assets/js/login.js
assets/js/app.js
app/
  bootstrap.php, Database.php, helpers.php
  models/       Usuario, Conta, Categoria, Lancamento
  controllers/  Auth, Conta, Categoria, Lancamento, Relatorio, Dashboard
config/config.php     Credenciais do MySQL
database/schema.sql   Script do banco (também pode ser importado no phpMyAdmin)
```

## Casos de uso implementados
| Caso de uso                         | Onde                                   | Rota da API                    |
|-------------------------------------|----------------------------------------|--------------------------------|
| Cadastrar-se                        | index.html → "Criar conta"             | POST auth/cadastro             |
| Fazer login / logout                | index.html / botão "Sair"              | POST auth/login, auth/logout   |
| Gerenciar contas                    | Menu "Contas"                          | conta/listar, salvar, excluir  |
| Gerenciar categorias                | Menu "Categorias"                      | categoria/listar, salvar, excluir |
| Registrar receita / despesa         | Botões "+ Receita" / "+ Despesa"       | POST lancamento/salvar         |
| Editar / excluir lançamento         | Menu "Lançamentos"                     | lancamento/salvar, excluir     |
| Atualizar saldo da conta («include») | Automático, em transação              | Conta::ajustarSaldo()          |
| Consultar saldo                     | Painel e menu "Contas"                 | dashboard/resumo, conta/listar |
| Relatório mensal por categoria      | Menu "Relatórios"                      | GET relatorio/mensal?mes=      |
| Filtrar por período e categoria («extend») | Relatórios → "Período" + categoria | relatorio/mensal?inicio=&fim=&categoria_id= |

## Segurança
- Senhas com `password_hash` / `password_verify` (bcrypt)
- Consultas com PDO + prepared statements
- Sessão PHP (`$_SESSION['usuario_id']`) com regeneração de ID no login
- Token CSRF exigido em todas as requisições POST
- Cada usuário só acessa as próprias contas, categorias e lançamentos
- `.htaccess` bloqueia acesso direto às pastas `app/`, `config/` e `database/`
- **Remova o `install.php` em produção.**
