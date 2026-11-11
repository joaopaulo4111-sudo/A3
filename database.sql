-- =====================================================================
--  AutoGestão - Sistema de Gerenciamento de Concessionária Multimarcas
--  Projeto Acadêmico A3 - Análise e Desenvolvimento de Sistemas
--  Arquivo: database.sql
--  Compatível com: MySQL 8.0+ e MariaDB 10.4+ (XAMPP)
-- =====================================================================
--  Ordem do script:
--    1. Criação do banco de dados
--    2. Criação das 8 tabelas (respeitando a ordem das chaves estrangeiras)
--    3. Inserção dos dados fictícios iniciais
--    4. Consultas de verificação (comentadas no final)
-- =====================================================================

-- Remove o banco caso já exista (útil para reimportar do zero durante os testes)
DROP DATABASE IF EXISTS autogestao;

-- utf8mb4 garante suporte completo a acentos (ex.: 'disponível', 'concluída')
CREATE DATABASE autogestao
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE autogestao;

SET NAMES utf8mb4;

-- =====================================================================
-- TABELA 1: clientes
-- Armazena os clientes da concessionária.
-- RN01: CPF único (UNIQUE).
-- =====================================================================
CREATE TABLE clientes (
    id_cliente     INT           NOT NULL AUTO_INCREMENT,
    nome           VARCHAR(120)  NOT NULL,
    cpf            VARCHAR(14)   NOT NULL,
    telefone       VARCHAR(20)   NOT NULL,
    email          VARCHAR(120)  NULL,
    endereco       VARCHAR(200)  NULL,
    data_cadastro  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_clientes       PRIMARY KEY (id_cliente),
    CONSTRAINT uq_clientes_cpf   UNIQUE (cpf),
    CONSTRAINT uq_clientes_email UNIQUE (email),

    -- Índices para acelerar as buscas por nome e telefone (RF12 / HU09)
    INDEX idx_clientes_nome (nome),
    INDEX idx_clientes_telefone (telefone)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA 2: funcionarios
-- Armazena a equipe da concessionária.
-- RN02: CPF único (UNIQUE).
-- =====================================================================
CREATE TABLE funcionarios (
    id_funcionario INT            NOT NULL AUTO_INCREMENT,
    nome           VARCHAR(120)   NOT NULL,
    cpf            VARCHAR(14)    NOT NULL,
    telefone       VARCHAR(20)    NOT NULL,
    email          VARCHAR(120)   NULL,
    cargo          VARCHAR(50)    NOT NULL,
    salario        DECIMAL(10,2)  NOT NULL,
    data_admissao  DATE           NOT NULL,

    CONSTRAINT pk_funcionarios         PRIMARY KEY (id_funcionario),
    CONSTRAINT uq_funcionarios_cpf     UNIQUE (cpf),
    CONSTRAINT uq_funcionarios_email   UNIQUE (email),
    CONSTRAINT ck_funcionarios_salario CHECK (salario >= 0),

    INDEX idx_funcionarios_nome (nome)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA 3: marcas
-- Fabricantes dos veículos (Toyota, Honda, ...).
-- =====================================================================
CREATE TABLE marcas (
    id_marca     INT          NOT NULL AUTO_INCREMENT,
    nome         VARCHAR(80)  NOT NULL,
    pais_origem  VARCHAR(60)  NULL,

    CONSTRAINT pk_marcas      PRIMARY KEY (id_marca),
    CONSTRAINT uq_marcas_nome UNIQUE (nome)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA 4: veiculos
-- Estoque de veículos novos e usados.
-- RN03: id_marca obrigatório e ligado a marcas (FOREIGN KEY + NOT NULL).
-- RN04: situação obrigatória (NOT NULL + DEFAULT 'disponível').
-- A placa é UNIQUE, mas pode ser NULL (veículos 0 km ainda sem placa).
-- No MySQL, vários registros NULL não violam a restrição UNIQUE.
-- =====================================================================
CREATE TABLE veiculos (
    id_veiculo      INT            NOT NULL AUTO_INCREMENT,
    id_marca        INT            NOT NULL,
    modelo          VARCHAR(100)   NOT NULL,
    ano_fabricacao  INT            NOT NULL,
    ano_modelo      INT            NOT NULL,
    tipo            ENUM('novo', 'usado') NOT NULL DEFAULT 'novo',
    cor             VARCHAR(40)    NOT NULL,
    placa           VARCHAR(10)    NULL,
    quilometragem   INT            NOT NULL DEFAULT 0,
    preco           DECIMAL(10,2)  NOT NULL,
    situacao        ENUM('disponível', 'vendido', 'reservado') NOT NULL DEFAULT 'disponível',
    descricao       TEXT           NULL,

    CONSTRAINT pk_veiculos        PRIMARY KEY (id_veiculo),
    CONSTRAINT uq_veiculos_placa  UNIQUE (placa),
    CONSTRAINT fk_veiculos_marcas FOREIGN KEY (id_marca)
        REFERENCES marcas (id_marca)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,           -- impede excluir marca que possui veículos
    CONSTRAINT ck_veiculos_preco  CHECK (preco > 0),
    CONSTRAINT ck_veiculos_km     CHECK (quilometragem >= 0),
    CONSTRAINT ck_veiculos_anos   CHECK (ano_fabricacao >= 1900
                                         AND ano_modelo >= ano_fabricacao
                                         AND ano_modelo <= ano_fabricacao + 1),

    -- Índices para os filtros de veículos (marca, modelo, ano, tipo, situação)
    INDEX idx_veiculos_modelo   (modelo),
    INDEX idx_veiculos_situacao (situacao),
    INDEX idx_veiculos_tipo     (tipo),
    INDEX idx_veiculos_ano      (ano_modelo)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA 5: vendas
-- Cabeçalho da venda.
-- RN06: cliente e funcionário obrigatórios (NOT NULL + FOREIGN KEY).
-- RN09: valor_total = soma dos valores vendidos dos veículos - desconto
--       (calculado pela aplicação PHP dentro de uma transação).
-- RN13: ON DELETE RESTRICT impede apagar clientes/funcionários com vendas.
-- =====================================================================
CREATE TABLE vendas (
    id_venda        INT            NOT NULL AUTO_INCREMENT,
    id_cliente      INT            NOT NULL,
    id_funcionario  INT            NOT NULL,
    data_venda      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_total     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    desconto        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    status          ENUM('pendente', 'concluída', 'cancelada') NOT NULL DEFAULT 'pendente',
    observacao      TEXT           NULL,

    CONSTRAINT pk_vendas              PRIMARY KEY (id_venda),
    CONSTRAINT fk_vendas_clientes     FOREIGN KEY (id_cliente)
        REFERENCES clientes (id_cliente)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_vendas_funcionarios FOREIGN KEY (id_funcionario)
        REFERENCES funcionarios (id_funcionario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT ck_vendas_valor_total  CHECK (valor_total >= 0),
    CONSTRAINT ck_vendas_desconto     CHECK (desconto >= 0),

    -- Índices para os filtros de vendas (período, funcionário, situação)
    INDEX idx_vendas_data   (data_venda),
    INDEX idx_vendas_status (status)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA 6: pagamentos
-- Pagamentos (parciais ou totais) de uma venda.
-- RN10: a soma dos pagamentos não cancelados não pode ultrapassar o
--       valor_total da venda (validado na aplicação PHP com transação).
-- =====================================================================
CREATE TABLE pagamentos (
    id_pagamento     INT            NOT NULL AUTO_INCREMENT,
    id_venda         INT            NOT NULL,
    valor            DECIMAL(10,2)  NOT NULL,
    forma_pagamento  ENUM('dinheiro', 'pix', 'cartão', 'transferência') NOT NULL,
    data_pagamento   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status           ENUM('pendente', 'pago', 'cancelado') NOT NULL DEFAULT 'pendente',

    CONSTRAINT pk_pagamentos        PRIMARY KEY (id_pagamento),
    CONSTRAINT fk_pagamentos_vendas FOREIGN KEY (id_venda)
        REFERENCES vendas (id_venda)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT ck_pagamentos_valor  CHECK (valor > 0),

    INDEX idx_pagamentos_data   (data_pagamento),
    INDEX idx_pagamentos_status (status)
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA 7: usuarios
-- Contas de acesso ao sistema.
-- Relacionamento Funcionários 1:0..1 Usuários:
--   id_funcionario é UNIQUE (um funcionário tem no máximo 1 usuário)
--   e pode ser NULL (usuário sem funcionário vinculado).
-- A senha é armazenada com password_hash() (bcrypt), nunca em texto puro.
-- RN12: status 'inativo' bloqueia o login (verificado no PHP).
-- =====================================================================
CREATE TABLE usuarios (
    id_usuario      INT           NOT NULL AUTO_INCREMENT,
    id_funcionario  INT           NULL,
    nome            VARCHAR(100)  NOT NULL,
    email           VARCHAR(120)  NOT NULL,
    senha           VARCHAR(255)  NOT NULL,
    perfil          ENUM('administrador', 'vendedor') NOT NULL DEFAULT 'vendedor',
    status          ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    data_criacao    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_usuarios              PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuarios_email        UNIQUE (email),
    CONSTRAINT uq_usuarios_funcionario  UNIQUE (id_funcionario),
    CONSTRAINT fk_usuarios_funcionarios FOREIGN KEY (id_funcionario)
        REFERENCES funcionarios (id_funcionario)
        ON UPDATE CASCADE
        ON DELETE SET NULL           -- se o funcionário sair, o usuário fica sem vínculo
) ENGINE=InnoDB;

-- =====================================================================
-- TABELA 8: venda_veiculos (tabela associativa)
-- Resolve o relacionamento N:N entre vendas e veículos.
-- PK composta (id_venda, id_veiculo).
-- RN08: id_veiculo é UNIQUE -> um veículo só pode aparecer em UMA venda.
-- =====================================================================
CREATE TABLE venda_veiculos (
    id_venda       INT            NOT NULL,
    id_veiculo     INT            NOT NULL,
    valor_vendido  DECIMAL(10,2)  NOT NULL,

    CONSTRAINT pk_venda_veiculos          PRIMARY KEY (id_venda, id_veiculo),
    CONSTRAINT uq_venda_veiculos_veiculo  UNIQUE (id_veiculo),
    CONSTRAINT fk_venda_veiculos_vendas   FOREIGN KEY (id_venda)
        REFERENCES vendas (id_venda)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_venda_veiculos_veiculos FOREIGN KEY (id_veiculo)
        REFERENCES veiculos (id_veiculo)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,           -- impede excluir veículo que já foi vendido
    CONSTRAINT ck_venda_veiculos_valor    CHECK (valor_vendido > 0)
) ENGINE=InnoDB;


-- =====================================================================
--                     DADOS FICTÍCIOS INICIAIS
-- =====================================================================

-- ---------------------------------------------------------------------
-- Marcas (10)
-- ---------------------------------------------------------------------
INSERT INTO marcas (id_marca, nome, pais_origem) VALUES
(1,  'Toyota',     'Japão'),
(2,  'Honda',      'Japão'),
(3,  'Volkswagen', 'Alemanha'),
(4,  'Chevrolet',  'Estados Unidos'),
(5,  'Fiat',       'Itália'),
(6,  'Hyundai',    'Coreia do Sul'),
(7,  'Jeep',       'Estados Unidos'),
(8,  'Renault',    'França'),
(9,  'Ford',       'Estados Unidos'),
(10, 'BMW',        'Alemanha');

-- ---------------------------------------------------------------------
-- Funcionários (6)
-- ---------------------------------------------------------------------
INSERT INTO funcionarios (id_funcionario, nome, cpf, telefone, email, cargo, salario, data_admissao) VALUES
(1, 'Ana Paula Ferreira',     '585.411.090-38', '(11) 98811-1001', 'ana.ferreira@autogestao.com',   'Gerente Comercial',      9500.00, '2019-03-01'),
(2, 'Carlos Eduardo Lima',    '918.967.609-29', '(11) 98822-2002', 'carlos.lima@autogestao.com',    'Vendedor',               3800.00, '2021-05-10'),
(3, 'Mariana Souza Rocha',    '529.178.606-00', '(11) 98833-3003', 'mariana.rocha@autogestao.com',  'Vendedora',              3800.00, '2022-02-14'),
(4, 'Rafael Oliveira Santos', '868.069.059-78', '(11) 98844-4004', 'rafael.santos@autogestao.com',  'Vendedor',               3600.00, '2023-08-01'),
(5, 'Juliana Martins Costa',  '520.887.226-96', '(11) 98855-5005', 'juliana.costa@autogestao.com',  'Assistente Financeiro',  4200.00, '2020-11-03'),
(6, 'Pedro Henrique Alves',   '827.630.360-90', '(11) 98866-6006', 'pedro.alves@autogestao.com',    'Vendedor',               3500.00, '2024-01-15');

-- ---------------------------------------------------------------------
-- Usuários (4)
-- Senhas (geradas com password_hash do PHP):
--   admin@autogestao.com   -> admin123     (administrador, ativo)
--   carlos@autogestao.com  -> vendedor123  (vendedor, ativo)
--   mariana@autogestao.com -> vendedor123  (vendedor, ativo)
--   pedro@autogestao.com   -> inativo123   (vendedor, INATIVO - para testar RN12)
-- ---------------------------------------------------------------------
INSERT INTO usuarios (id_usuario, id_funcionario, nome, email, senha, perfil, status) VALUES
(1, 1, 'Ana Paula Ferreira',   'admin@autogestao.com',   '$2y$10$cMPqJbokAXrCx3lNgggi5O5sTZF1N54Zg357aO3JTPrf18FWMhRY2', 'administrador', 'ativo'),
(2, 2, 'Carlos Eduardo Lima',  'carlos@autogestao.com',  '$2y$10$C6UYO.KQmADINbA.471DvuYtBpB/wVeK8DUBL2PR.vL4hv9lD.REm', 'vendedor',      'ativo'),
(3, 3, 'Mariana Souza Rocha',  'mariana@autogestao.com', '$2y$10$C6UYO.KQmADINbA.471DvuYtBpB/wVeK8DUBL2PR.vL4hv9lD.REm', 'vendedor',      'ativo'),
(4, 6, 'Pedro Henrique Alves', 'pedro@autogestao.com',   '$2y$10$o9WX147p5uptOlNpm7NTSeOLU.HxkWhVBWy1K.JRUIlJeFlV97vq6', 'vendedor',      'inativo');

-- ---------------------------------------------------------------------
-- Clientes (12)
-- ---------------------------------------------------------------------
INSERT INTO clientes (id_cliente, nome, cpf, telefone, email, endereco, data_cadastro) VALUES
(1,  'João Pedro Almeida',      '276.465.040-09', '(11) 97001-1111', 'joao.almeida@email.com',    'Rua das Flores, 120 - Centro, São Paulo/SP',         '2026-05-20 10:15:00'),
(2,  'Fernanda Ribeiro Gomes',  '384.041.255-20', '(11) 97002-2222', 'fernanda.gomes@email.com',  'Av. Paulista, 1500 - Bela Vista, São Paulo/SP',      '2026-06-02 14:30:00'),
(3,  'Lucas Henrique Barbosa',  '769.631.931-00', '(11) 97003-3333', 'lucas.barbosa@email.com',   'Rua Augusta, 845 - Consolação, São Paulo/SP',        '2026-07-11 09:45:00'),
(4,  'Beatriz Carvalho Nunes',  '979.205.934-25', '(11) 97004-4444', 'beatriz.nunes@email.com',   'Rua Vergueiro, 3200 - Vila Mariana, São Paulo/SP',   '2026-08-01 16:20:00'),
(5,  'Rodrigo Mendes Pereira',  '962.062.797-07', '(11) 97005-5555', 'rodrigo.pereira@email.com', 'Av. Santo Amaro, 700 - Brooklin, São Paulo/SP',      '2026-09-14 11:00:00'),
(6,  'Camila Teixeira Duarte',  '334.370.909-33', '(11) 97006-6666', 'camila.duarte@email.com',   'Rua Oscar Freire, 410 - Jardins, São Paulo/SP',      '2026-10-05 13:10:00'),
(7,  'Gustavo Araújo Moreira',  '695.488.600-90', '(11) 97007-7777', 'gustavo.moreira@email.com', 'Rua Domingos de Morais, 950 - Saúde, São Paulo/SP',  '2026-10-20 15:40:00'),
(8,  'Patrícia Lopes Cardoso',  '001.301.179-00', '(11) 97008-8888', 'patricia.cardoso@email.com','Av. Ibirapuera, 2300 - Moema, São Paulo/SP',         '2026-11-01 10:05:00'),
(9,  'Thiago Fernandes Silva',  '438.937.814-71', '(11) 97009-9999', 'thiago.silva@email.com',    'Rua Teodoro Sampaio, 600 - Pinheiros, São Paulo/SP', '2026-11-03 17:25:00'),
(10, 'Larissa Monteiro Dias',   '163.209.853-99', '(11) 97010-1010', 'larissa.dias@email.com',    'Rua Cardeal Arcoverde, 1200 - Pinheiros, São Paulo/SP','2026-11-05 08:50:00'),
(11, 'Marcelo Azevedo Ramos',   '775.915.191-82', '(11) 97011-1212', NULL,                        'Av. Rebouças, 3000 - Pinheiros, São Paulo/SP',       '2026-11-07 12:30:00'),
(12, 'Vanessa Cristina Prado',  '403.757.157-97', '(11) 97012-1313', 'vanessa.prado@email.com',   NULL,                                                  '2026-11-09 09:15:00');

-- ---------------------------------------------------------------------
-- Veículos (20)
-- Situações coerentes com as vendas abaixo:
--   vendido   -> veículos de vendas concluídas
--   reservado -> veículo de venda pendente
--   disponível-> restante do estoque
-- ---------------------------------------------------------------------
INSERT INTO veiculos (id_veiculo, id_marca, modelo, ano_fabricacao, ano_modelo, tipo, cor, placa, quilometragem, preco, situacao, descricao) VALUES
(1,  1,  'Corolla XEi 2.0',          2024, 2025, 'novo',  'Prata',    NULL,      0,     152990.00, 'vendido',    'Sedã automático CVT, central multimídia e Toyota Safety Sense.'),
(2,  2,  'HR-V EXL 1.5',             2023, 2024, 'usado', 'Branco',   'RTA1B23', 18500, 139900.00, 'vendido',    'Único dono, revisões na concessionária, teto solar.'),
(3,  5,  'Strada Volcano 1.3',       2024, 2024, 'novo',  'Vermelho', NULL,      0,     119990.00, 'vendido',    'Picape cabine dupla, câmbio CVT.'),
(4,  3,  'T-Cross Highline 250 TSI', 2022, 2023, 'usado', 'Cinza',    'QWE2C45', 32000, 124500.00, 'vendido',    'SUV turbo, painel digital, bancos em couro.'),
(5,  4,  'Onix Plus LTZ 1.0 Turbo',  2025, 2025, 'novo',  'Preto',    NULL,      0,     109990.00, 'vendido',    'Sedã compacto com Wi-Fi nativo e OnStar.'),
(6,  7,  'Compass Longitude 1.3T',   2021, 2022, 'usado', 'Branco',   'BRA3D67', 45000, 139000.00, 'vendido',    'Laudo cautelar aprovado, pneus novos.'),
(7,  6,  'Creta Platinum 1.0T',      2025, 2026, 'novo',  'Azul',     NULL,      0,     159990.00, 'reservado',  'Versão topo de linha, teto solar panorâmico.'),
(8,  8,  'Kwid Zen 1.0',             2023, 2023, 'usado', 'Branco',   'KWD4E89', 22000, 58900.00,  'vendido',    'Econômico, ideal para uso urbano.'),
(9,  1,  'Hilux SRX 2.8 Diesel 4x4', 2025, 2025, 'novo',  'Branco',   NULL,      0,     329990.00, 'disponível', 'Picape diesel 4x4 automática, 204 cv.'),
(10, 2,  'Civic Touring 1.5 Turbo',  2021, 2021, 'usado', 'Preto',    'CVC5F12', 51000, 154900.00, 'disponível', 'Revisado, manual e chave reserva.'),
(11, 3,  'Polo Highline 1.0 TSI',    2024, 2025, 'novo',  'Cinza',    NULL,      0,     112990.00, 'disponível', 'Hatch turbo com ACC e frenagem autônoma.'),
(12, 4,  'Tracker Premier 1.2T',     2023, 2024, 'usado', 'Prata',    'TRK6G34', 27000, 129900.00, 'disponível', 'Teto solar, carregador por indução.'),
(13, 5,  'Pulse Impetus 1.0 Turbo',  2025, 2025, 'novo',  'Vermelho', NULL,      0,     121990.00, 'disponível', 'SUV compacto turbo flex.'),
(14, 9,  'Ranger XLS 2.2 Diesel',    2022, 2022, 'usado', 'Cinza',    'RGR7H56', 68000, 189900.00, 'disponível', 'Picape diesel 4x4, capota marítima.'),
(15, 10, '320i M Sport 2.0',         2024, 2024, 'novo',  'Azul',     NULL,      0,     349950.00, 'disponível', 'Sedã premium, pacote M Sport.'),
(16, 7,  'Renegade Sport 1.8',       2020, 2021, 'usado', 'Verde',    'RNG8I78', 74000, 89900.00,  'disponível', 'Bom estado de conservação.'),
(17, 6,  'HB20 Platinum 1.0T',       2024, 2025, 'novo',  'Branco',   NULL,      0,     104990.00, 'disponível', 'Hatch turbo com Bluelink.'),
(18, 8,  'Duster Iconic 1.3T',       2023, 2023, 'usado', 'Marrom',   'DST9J90', 39000, 104500.00, 'disponível', 'SUV turbo, câmera 360°.'),
(19, 1,  'Yaris XLS 1.5',            2022, 2023, 'usado', 'Prata',    'YRS0K12', 35500, 92900.00,  'disponível', 'Liberado após cancelamento da venda #7.'),
(20, 3,  'Nivus Highline 1.0 TSI',   2025, 2026, 'novo',  'Branco',   NULL,      0,     145990.00, 'disponível', 'SUV coupé com painel digital.');

-- ---------------------------------------------------------------------
-- Vendas (8)
-- valor_total = soma(valor_vendido) - desconto  (RN09)
-- ---------------------------------------------------------------------
INSERT INTO vendas (id_venda, id_cliente, id_funcionario, data_venda, valor_total, desconto, status, observacao) VALUES
(1, 1, 2, '2026-06-12 10:30:00', 150000.00, 2990.00, 'concluída', 'Pagamento em duas transferências.'),
(2, 2, 3, '2026-07-20 15:00:00', 250000.00, 9890.00, 'concluída', 'Compra de dois veículos para a empresa da cliente.'),
(3, 3, 2, '2026-08-15 11:20:00', 120000.00, 4500.00, 'concluída', 'Saldo restante será pago em parcelas.'),
(4, 4, 4, '2026-09-05 09:40:00', 109990.00, 0.00,    'concluída', NULL),
(5, 5, 3, '2026-10-10 16:10:00', 135000.00, 4000.00, 'concluída', 'Cliente indicado por outro comprador.'),
(6, 6, 2, '2026-10-28 14:00:00', 159990.00, 0.00,    'pendente',  'Aguardando aprovação do financiamento. Sinal pago.'),
(7, 7, 4, '2026-11-03 10:00:00', 92900.00,  0.00,    'cancelada', 'Venda cancelada a pedido do cliente. Veículo liberado: #19 Toyota Yaris XLS 1.5 (R$ 92.900,00).'),
(8, 8, 3, '2026-11-08 13:30:00', 58000.00,  900.00,  'concluída', 'Pagamento à vista via PIX.');

-- ---------------------------------------------------------------------
-- Itens das vendas (venda_veiculos)
-- A venda 7 (cancelada) não possui itens: ao cancelar, o vínculo é
-- removido para liberar o veículo (ver explicação na ETAPA 1).
-- ---------------------------------------------------------------------
INSERT INTO venda_veiculos (id_venda, id_veiculo, valor_vendido) VALUES
(1, 1, 152990.00),   -- Corolla      152.990 - 2.990 desc = 150.000
(2, 2, 139900.00),   -- HR-V
(2, 3, 119990.00),   -- Strada       259.890 - 9.890 desc = 250.000
(3, 4, 124500.00),   -- T-Cross      124.500 - 4.500 desc = 120.000
(4, 5, 109990.00),   -- Onix Plus    109.990 - 0     desc = 109.990
(5, 6, 139000.00),   -- Compass      139.000 - 4.000 desc = 135.000
(6, 7, 159990.00),   -- Creta        159.990 - 0     desc = 159.990 (pendente)
(8, 8, 58900.00);    -- Kwid          58.900 - 900   desc =  58.000

-- ---------------------------------------------------------------------
-- Pagamentos (12)
-- Pagamentos cancelados não entram no cálculo do valor recebido.
-- ---------------------------------------------------------------------
INSERT INTO pagamentos (id_pagamento, id_venda, valor, forma_pagamento, data_pagamento, status) VALUES
(1,  1, 50000.00,  'transferência', '2026-06-12 10:45:00', 'pago'),
(2,  1, 100000.00, 'transferência', '2026-06-20 09:00:00', 'pago'),
(3,  2, 100000.00, 'pix',           '2026-07-20 15:20:00', 'pago'),
(4,  2, 150000.00, 'cartão',        '2026-07-25 11:00:00', 'pago'),
(5,  3, 60000.00,  'pix',           '2026-08-15 11:30:00', 'pago'),
(6,  3, 30000.00,  'transferência', '2026-09-15 10:00:00', 'pago'),
(7,  4, 109990.00, 'transferência', '2026-09-05 10:00:00', 'pago'),
(8,  5, 135000.00, 'pix',           '2026-10-10 16:30:00', 'pago'),
(9,  6, 20000.00,  'pix',           '2026-10-28 14:15:00', 'pago'),
(10, 6, 40000.00,  'transferência', '2026-11-20 10:00:00', 'pendente'),
(11, 7, 5000.00,   'pix',           '2026-11-03 10:10:00', 'cancelado'),
(12, 8, 58000.00,  'pix',           '2026-11-08 13:45:00', 'pago');

-- =====================================================================
--  CONSULTAS DE VERIFICAÇÃO (execute na aba SQL do phpMyAdmin)
-- =====================================================================
-- 1) Quantidade de registros por tabela:
-- SELECT 'clientes' AS tabela, COUNT(*) AS total FROM clientes
-- UNION ALL SELECT 'funcionarios',   COUNT(*) FROM funcionarios
-- UNION ALL SELECT 'marcas',         COUNT(*) FROM marcas
-- UNION ALL SELECT 'veiculos',       COUNT(*) FROM veiculos
-- UNION ALL SELECT 'vendas',         COUNT(*) FROM vendas
-- UNION ALL SELECT 'pagamentos',     COUNT(*) FROM pagamentos
-- UNION ALL SELECT 'usuarios',       COUNT(*) FROM usuarios
-- UNION ALL SELECT 'venda_veiculos', COUNT(*) FROM venda_veiculos;
--
-- 2) Vendas com cliente, vendedor, total, recebido e saldo:
-- SELECT v.id_venda, c.nome AS cliente, f.nome AS vendedor, v.status,
--        v.valor_total,
--        COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS recebido,
--        v.valor_total - COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS saldo
--   FROM vendas v
--   JOIN clientes c     ON c.id_cliente = v.id_cliente
--   JOIN funcionarios f ON f.id_funcionario = v.id_funcionario
--   LEFT JOIN pagamentos p ON p.id_venda = v.id_venda
--  GROUP BY v.id_venda, c.nome, f.nome, v.status, v.valor_total
--  ORDER BY v.id_venda;
--
-- 3) Conferência da RN09 (deve retornar 0 linhas, exceto vendas canceladas):
-- SELECT v.id_venda, v.valor_total, SUM(vv.valor_vendido) - v.desconto AS calculado
--   FROM vendas v JOIN venda_veiculos vv ON vv.id_venda = v.id_venda
--  GROUP BY v.id_venda, v.valor_total, v.desconto
-- HAVING v.valor_total <> calculado;
--
-- 4) Teste de integridade (deve FALHAR - CPF duplicado, RN01):
-- INSERT INTO clientes (nome, cpf, telefone) VALUES ('Teste', '276.465.040-09', '(11) 90000-0000');
--
-- 5) Teste de integridade (deve FALHAR - veículo já vendido, RN08):
-- INSERT INTO venda_veiculos (id_venda, id_veiculo, valor_vendido) VALUES (6, 1, 1000.00);
--
-- 6) Teste de integridade (deve FALHAR - marca inexistente, RN03):
-- INSERT INTO veiculos (id_marca, modelo, ano_fabricacao, ano_modelo, cor, preco) VALUES (999, 'Teste', 2024, 2024, 'Preto', 50000);
--
-- 7) Teste de integridade (deve FALHAR - excluir cliente com vendas, RN13):
-- DELETE FROM clientes WHERE id_cliente = 1;
-- =====================================================================
