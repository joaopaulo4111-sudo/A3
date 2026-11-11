# Magnata Automóveis

Sistema web de gestão para concessionária multimarcas, desenvolvido em PHP com MySQL, Bootstrap e exportação de relatórios em PDF.

## Visão geral

O projeto tem como objetivo centralizar o controle de:

- clientes
- funcionários e usuários
- marcas
- veículos em estoque
- vendas
- pagamentos
- relatórios e dashboard gerencial

A aplicação foi pensada para simular um ambiente real de concessionária premium, com autenticação, permissões por perfil, transações de venda e controle financeiro.

## Tecnologias

- PHP 8+
- MySQL / MariaDB
- PDO para acesso ao banco
- Bootstrap 5
- Dompdf para geração de PDF
- HTML, CSS e JavaScript

## Estrutura do projeto

```text
A3/
├── auth/
│   └── login.php
├── assets/
│   ├── css/
│   ├── img/
│   └── js/
├── clientes/
├── config/
│   └── database.php
├── funcionarios/
├── includes/
│   ├── auth.php
│   ├── footer.php
│   ├── functions.php
│   ├── header.php
│   └── sidebar.php
├── marcas/
├── pagamentos/
├── relatorios/
├── veiculos/
├── vendas/
├── composer.json
├── database.sql
├── dashboard.php
├── DOCUMENTACAO_A3.md
├── index.php
├── README.md
└── vendor/
```

## Requisitos

- XAMPP com Apache + MySQL
- PHP 8.0 ou superior
- Composer (opcional, mas já incluído para Dompdf)

## Configuração do ambiente

1. Coloque a pasta do projeto em `htdocs` do XAMPP.
2. Inicie o Apache e o MySQL no painel do XAMPP.
3. Importa o arquivo `database.sql` no MySQL para criar o banco `autogestao` e inserir dados iniciais.
4. Ajuste as credenciais no arquivo `config/database.php`, se necessário.

Arquivo padrão:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'autogestao');
define('DB_USER', 'root');
define('DB_PASS', 'ceub123456');
```

## Execução

Acesse no navegador:

```text
http://localhost/A3
```

O sistema redireciona para a tela de login automaticamente.

## Usuários de demonstração

Os dados iniciais já incluem usuários para teste:

- Administrador: `admin@autogestao.com` / `admin123`
- Vendedor: `carlos@autogestao.com` / `vendedor123`
- Usuário inativo: `pedro@autogestao.com` / `inativo123`

## Funcionalidades principais

- autenticação e controle de sessão
- cadastro de clientes, funcionários, marcas e veículos
- gestão de estoque por situação (`disponível`, `reservado`, `vendido`)
- registro de vendas e itens vinculados
- controle de pagamentos e saldo devedor
- dashboard com indicadores operacionais
- relatórios de estoque e vendas
- exportação para PDF

## Banco de dados

O banco principal é:

```text
autogestao
```

O script `database.sql` cria a estrutura, índices, constraints e dados iniciais de teste.

## Documentação do projeto

Mais detalhes técnicos e acadêmicos estão no arquivo:

- `DOCUMENTACAO_A3.md`

## Observações

- O projeto foi concebido para fins acadêmicos.
- A senha dos usuários é armazenada com `password_hash`, seguindo boa prática de segurança.
- As transações de venda e pagamento são tratadas via PDO para manter consistência de dados.

## Licença

Este projeto foi desenvolvido para fins educacionais e de avaliação acadêmica.
