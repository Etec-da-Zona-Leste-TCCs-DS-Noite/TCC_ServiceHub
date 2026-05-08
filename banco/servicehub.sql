-- banco/servicehub.sql
CREATE DATABASE IF NOT EXISTS servicehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE servicehub;

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    endereco TEXT,
    cpf_cnpj VARCHAR(20),
    tipo ENUM('fisica','juridica') DEFAULT 'fisica',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_empresa VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    endereco TEXT,
    descricao TEXT,
    logo VARCHAR(255),
    site VARCHAR(100),
    status TINYINT DEFAULT 1 COMMENT '1=Ativa, 0=Inativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NULL COMMENT 'NULL = valor a definir',
    duracao_estimada INT COMMENT 'Duração em horas',
    categoria VARCHAR(50),
    status TINYINT DEFAULT 1 COMMENT '1=Ativo, 0=Inativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

CREATE TABLE orcamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    servico_id INT,
    empresa_id INT,
    quantidade INT DEFAULT 1,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pendente','aprovado','rejeitado','concluido','expirado') DEFAULT 'pendente',
    observacoes TEXT,
    data_orcamento DATE NOT NULL,
    data_validade DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL
);

CREATE TABLE orcamento_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT NOT NULL,
    servico_id INT NOT NULL,
    quantidade INT DEFAULT 1,
    valor_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id)
);

-- ══════════════════════════════════════════
--  TABELA DE AVALIAÇÕES  (novo)
-- ══════════════════════════════════════════
CREATE TABLE avaliacoes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    orcamento_id  INT NOT NULL,
    cliente_id    INT NOT NULL,
    empresa_id    INT NOT NULL,
    nota          TINYINT NOT NULL COMMENT '1 a 5 estrelas',
    titulo        VARCHAR(100),
    comentario    TEXT,
    resposta      TEXT    COMMENT 'Resposta da empresa',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT nota_range CHECK (nota BETWEEN 1 AND 5),
    FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id)  ON DELETE CASCADE,
    FOREIGN KEY (cliente_id)   REFERENCES clientes(id)    ON DELETE CASCADE,
    FOREIGN KEY (empresa_id)   REFERENCES empresas(id)    ON DELETE CASCADE,
    UNIQUE KEY uq_avaliacao_orcamento (orcamento_id)  -- uma avaliação por orçamento
);

-- Banco iniciado sem dados de exemplo. Cadastre empresas, clientes e serviços pelo sistema.

-- Colunas para recuperação de senha
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS reset_expira DATETIME NULL;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS reset_expira DATETIME NULL;

-- Permitir valor NULL em serviços (valor a definir)
ALTER TABLE servicos MODIFY COLUMN valor DECIMAL(10,2) NULL COMMENT 'NULL = valor a definir';


CREATE TABLE IF NOT EXISTS conversas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id   INT NOT NULL,
    empresa_id   INT NOT NULL,
    orcamento_id INT NULL COMMENT 'Opcional: chat sobre um orçamento específico',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id)   REFERENCES clientes(id)   ON DELETE CASCADE,
    FOREIGN KEY (empresa_id)   REFERENCES empresas(id)   ON DELETE CASCADE,
    FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE SET NULL,
    UNIQUE KEY uq_conversa (cliente_id, empresa_id)
);

CREATE TABLE IF NOT EXISTS mensagens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id  INT NOT NULL,
    remetente    ENUM('cliente','empresa') NOT NULL,
    conteudo     TEXT NOT NULL,
    lida         TINYINT DEFAULT 0 COMMENT '0=não lida, 1=lida',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES conversas(id) ON DELETE CASCADE
);

-- Sem dados de exemplo para conversas e mensagens.

-- Tabela de status "digitando" (auto-criada via typing.php, mas aqui para deploy completo)
CREATE TABLE IF NOT EXISTS chat_typing (
    conversa_id INT NOT NULL,
    remetente   ENUM('cliente','empresa') NOT NULL,
    status      TINYINT DEFAULT 0,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (conversa_id, remetente)
) ENGINE=InnoDB;


-- ══════════════════════════════════════════
--  GEOLOCALIZAÇÃO  (novo)
-- ══════════════════════════════════════════

-- Adiciona colunas de latitude e longitude na tabela de empresas
ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10,7) NULL COMMENT 'Latitude geocodificada',
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL COMMENT 'Longitude geocodificada';

-- Índice para buscas por proximidade (opcional, melhora performance)
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL;

-- Coordenadas serão preenchidas automaticamente via geocodificação ao cadastrar endereço.

-- ══════════════════════════════════════════
--  PAINEL ADMIN (novo)
-- ══════════════════════════════════════════
-- Acesso: /admin/login.php
-- Usuário: admin  |  Senha: admin@servicehub2024
-- NOTA: Em produção, troque a senha e atualize o hash em admin/login.php

