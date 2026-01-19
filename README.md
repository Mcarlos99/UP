# 📦 PapelOn - Sistema de Gestão para Papelaria

Sistema completo de gestão para papelarias personalizadas desenvolvido em PHP e MySQL.

## 🚀 Funcionalidades

- ✅ **Dashboard** - Visão geral com métricas e estatísticas
- ✅ **Gestão de Pedidos** - Controle completo de pedidos com status
- ✅ **Catálogo de Produtos** - Gerenciamento de produtos e estoque
- ✅ **Base de Clientes** - CRM completo com histórico
- ✅ **Financeiro** - Controle de receitas e despesas
- ✅ **Relatórios** - Análises e insights do negócio
- ✅ **Sistema de Login** - Autenticação segura
- ✅ **Controle de Acesso** - Níveis de permissão (Admin/Usuário)
- ✅ **Log de Atividades** - Auditoria completa do sistema

## 📋 Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache ou Nginx
- Extensões PHP: PDO, PDO_MySQL, mbstring

## 🔧 Instalação

### 1. Upload dos Arquivos

Faça upload de todos os arquivos para o seu servidor:
```
www.extremesti.com.br/up/
```

### 2. Criar o Banco de Dados

1. Acesse o **phpMyAdmin** do seu servidor
2. Crie um novo banco de dados chamado `papelaria_system`
3. Importe o arquivo `database.sql` no banco criado

### 3. Configurar Conexão

Edite o arquivo `config.php` e ajuste as configurações:

```php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');           // Host do banco
define('DB_NAME', 'papelaria_system');    // Nome do banco
define('DB_USER', 'seu_usuario');         // Usuário do banco
define('DB_PASS', 'sua_senha');           // Senha do banco

// URL do Sistema
define('SITE_URL', 'http://www.extremesti.com.br/up/');
```

### 4. Criar Pasta de Uploads

Crie a pasta `uploads/` na raiz do projeto e dê permissões de escrita:

```bash
mkdir uploads
chmod 777 uploads
```

### 5. Acessar o Sistema

Acesse: `http://www.extremesti.com.br/up/`

**Dados de acesso padrão:**
- Email: `admin@papelaria.com`
- Senha: `admin123`

⚠️ **IMPORTANTE:** Altere a senha padrão após o primeiro acesso!

## 📁 Estrutura de Arquivos

```
/
├── config.php           # Configurações do sistema
├── database.sql         # Script de criação do banco
├── index.php           # Página de login
├── dashboard.php       # Dashboard principal
├── header.php          # Header (incluído em todas as páginas)
├── footer.php          # Footer (incluído em todas as páginas)
├── logout.php          # Script de logout
├── pedidos.php         # Gestão de pedidos
├── produtos.php        # Gestão de produtos
├── clientes.php        # Gestão de clientes
├── financeiro.php      # Gestão financeira
├── relatorios.php      # Relatórios
├── configuracoes.php   # Configurações do sistema
└── uploads/            # Pasta para arquivos enviados
    ├── produtos/       # Imagens de produtos
    ├── clientes/       # Arquivos de clientes
    └── comprovantes/   # Comprovantes financeiros
```

## 🎯 Próximos Passos - Arquivos a Criar

Os seguintes arquivos ainda precisam ser criados:

1. **pedidos.php** - Gestão completa de pedidos
2. **pedido-novo.php** - Formulário para criar novos pedidos
3. **pedido-editar.php** - Formulário para editar pedidos
4. **produtos.php** - Listagem e gestão de produtos
5. **produto-form.php** - Formulário de produtos
6. **clientes.php** - Listagem e gestão de clientes
7. **cliente-form.php** - Formulário de clientes
8. **financeiro.php** - Controle financeiro
9. **relatorios.php** - Relatórios e análises
10. **configuracoes.php** - Configurações do sistema

## 🔐 Segurança

- Senhas são criptografadas com `password_hash()`
- Proteção contra SQL Injection usando PDO Prepared Statements
- Validação de sessões em todas as páginas
- Log de atividades para auditoria
- Sanitização de inputs do usuário

## 🎨 Design

- Interface moderna e responsiva
- Cores: Gradientes roxo/rosa/azul
- Framework CSS: Tailwind CSS
- Ícones: Font Awesome
- Compatível com dispositivos móveis

## 💡 Dicas de Uso

1. **Backup Regular**: Faça backup do banco de dados regularmente
2. **Permissões**: Configure corretamente as permissões da pasta uploads/
3. **SSL**: Use HTTPS em produção para maior segurança
4. **Logs**: Monitore os logs de atividades regularmente

## 🐛 Solução de Problemas

### Erro de Conexão com Banco de Dados
- Verifique as credenciais em `config.php`
- Certifique-se que o banco de dados existe
- Verifique se o usuário tem permissões

### Erro de Upload
- Verifique as permissões da pasta `uploads/`
- Aumente o `upload_max_filesize` no php.ini se necessário

### Página em Branco
- Ative a exibição de erros no `config.php`
- Verifique os logs de erro do PHP

## 📞 Suporte

Para dúvidas ou problemas:
- Email: suporte@papelaria.com
- Documentação: Incluída no sistema

## 📝 Changelog

### Versão 1.0.0 (2025-01-18)
- Lançamento inicial do sistema
- Dashboard com métricas principais
- Sistema de autenticação
- Estrutura base do banco de dados
- Configurações iniciais

## 📄 Licença

Este sistema foi desenvolvido para uso pessoal/comercial.

---

**Desenvolvido com ❤️ para facilitar a gestão de papelarias personalizadas**
