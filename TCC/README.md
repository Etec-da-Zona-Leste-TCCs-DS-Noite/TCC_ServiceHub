# ServiceHub 🔧

Plataforma web para conexão entre clientes e empresas prestadoras de serviços.  
Desenvolvido em **PHP + MySQL** como projeto de TCC.

---

## 📋 Requisitos

| Tecnologia | Versão mínima |
|---|---|
| PHP | 7.4+ |
| MySQL / MariaDB | 5.7+ |
| Servidor web | Apache (com mod_rewrite) |

---

## 🚀 Como instalar localmente (XAMPP / WAMP)

1. **Baixe e instale o XAMPP** em [apachefriends.org](https://www.apachefriends.org)

2. **Copie o projeto** para a pasta `htdocs`:
   ```
   C:\xampp\htdocs\servicehub\
   ```

3. **Crie o banco de dados:**
   - Inicie o Apache e o MySQL no painel do XAMPP
   - Acesse `http://localhost/phpmyadmin`
   - Clique em **"Importar"** e selecione o arquivo `banco/servicehub.sql`
   - Clique em **"Executar"**

4. **Configure a conexão** em `includes/config.php`:
   ```php
   $host     = 'localhost';
   $dbname   = 'servicehub';
   $username = 'root';
   $password = '';   // padrão do XAMPP
   ```

5. **Acesse no navegador:** `http://localhost/servicehub/`

---

## 👤 Usuários de teste

| Tipo | E-mail | Senha |
|---|---|---|
| Cliente | joao@email.com | 123456 |
| Cliente | maria@email.com | 123456 |
| Empresa | contato@techsolutions.com | 123456 |
| Empresa | contato@designpro.com | 123456 |
| Empresa | contato@suportetotal.com | 123456 |

---

## ☁️ Como publicar na internet (Hostinger)

1. Acesse [hostinger.com.br](https://www.hostinger.com.br) e escolha um plano (mínimo Plano Web)
2. No **hPanel**, crie um banco de dados MySQL e anote:
   - Nome do banco
   - Usuário
   - Senha
   - Host (geralmente `localhost` ou similar)
3. Faça upload dos arquivos via **File Manager** ou **FTP (FileZilla)**
4. Edite `includes/config.php` com as credenciais do banco
5. No hPanel → Banco de Dados → phpMyAdmin → Importe `banco/servicehub.sql`
6. Pronto! O site já estará no ar pelo seu domínio.

---

## 📁 Estrutura do projeto

```
servicehub/
├── index.php               ← Login / página inicial
├── logout.php
├── esqueci_senha.php       ← Recuperação de senha
├── redefinir_senha.php
├── 404.php                 ← Página de erro personalizada
├── dashboard_cliente.php
├── dashboard_empresa.php
├── .htaccess               ← Configurações Apache (segurança + 404)
│
├── includes/
│   ├── config.php          ← Conexão com o banco
│   ├── auth.php            ← Funções de login/sessão
│   └── functions.php       ← Utilitários gerais
│
├── css/
│   └── estilo.css          ← Design System completo
│
├── banco/
│   └── servicehub.sql      ← Script de criação do banco
│
├── clientes/
│   ├── cadastro.php
│   ├── perfil.php          ← Edição de dados do cliente
│   ├── empresas.php        ← Listagem de empresas
│   ├── empresa.php         ← Página de uma empresa
│   └── ...
│
├── empresas/
│   ├── cadastro.php
│   ├── perfil.php          ← Edição de dados da empresa
│   ├── meus_servicos.php
│   └── ...
│
├── orcamentos/
│   ├── index.php           ← Listagem
│   ├── create.php          ← Criar
│   ├── view.php            ← Visualizar / alterar status
│   ├── edit.php
│   └── delete.php
│
├── servicos/
│   └── ...
│
└── relatorios/
    └── index.php           ← Dashboard de relatórios (empresa)
```

---

## 🔒 Segurança implementada

- Senhas com `password_hash()` (bcrypt) — sem MD5
- Prepared statements em todas as queries (proteção contra SQL Injection)
- `htmlspecialchars()` em todos os outputs (proteção contra XSS)
- Verificação de sessão e tipo de usuário em cada página
- Headers de segurança via `.htaccess`
- Tokens de recuperação de senha com expiração de 1 hora

---

## 🛠️ Melhorias futuras sugeridas

- [ ] Upload de logo para empresas
- [x] Sistema de avaliações / reviews ✅
- [ ] Notificações por e-mail (configurar SMTP)
- [x] Chat entre cliente e empresa ✅
- [x] Painel administrativo (admin) ✅ — acesse em `/admin/` (usuário: `admin`, senha: `admin@servicehub2024`)
- [ ] Exportação de relatórios em PDF
---

## 📝 Changelog — Correções e Adições

### 🐛 Bugs corrigidos
- **`servicos/index.php`** — corrigido bug crítico onde a lista de serviços mostrava serviços de **todas** as empresas em vez de apenas os da empresa logada
- **`css/estilo.css`** — adicionados aliases de variáveis CSS faltantes (`--radius`, `--radius-sm`, `--shadow`, `--transition`, `--teal`) que faziam o layout ficar sem borda-arredondada e sem sombras em vários componentes
- **`orcamentos/view.php`** — cliente agora pode marcar orçamento como "concluído" quando está aprovado, além de rejeitar/cancelar

### ✨ Novidades implementadas
- **Painel Admin** (`/admin/`) — dashboard completo com:
  - Login separado (usuário: `admin`, senha: `admin@servicehub2024`)
  - Dashboard com KPIs globais e gráfico de faturamento mensal
  - Gerenciamento de empresas (ativar/desativar/excluir)
  - Gerenciamento de clientes (listagem/excluir)
  - Visão global de todos os orçamentos com filtros
  - Moderação de avaliações
  - Listagem global de todos os serviços
