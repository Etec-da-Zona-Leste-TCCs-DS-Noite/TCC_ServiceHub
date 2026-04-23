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
    valor DECIMAL(10,2) NOT NULL,
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

-- Dados de exemplo
INSERT INTO clientes (nome, email, senha, telefone) VALUES
('João Silva',   'joao@email.com',  MD5('123456'), '(11) 99999-1111'),
('Maria Santos', 'maria@email.com', MD5('123456'), '(11) 99999-2222');

INSERT INTO empresas (nome_empresa, email, senha, cnpj, telefone, descricao) VALUES
('Tech Solutions', 'contato@techsolutions.com', MD5('123456'), '12.345.678/0001-90', '(11) 3333-4444', 'Soluções em tecnologia e desenvolvimento web'),
('Design Pro',     'contato@designpro.com',     MD5('123456'), '98.765.432/0001-10', '(11) 5555-6666', 'Agência de design gráfico e marketing digital'),
('Suporte Total',  'contato@suportetotal.com',  MD5('123456'), '45.678.901/0001-23', '(11) 7777-8888', 'Suporte técnico e manutenção de sistemas');

INSERT INTO servicos (empresa_id, nome, descricao, valor, categoria) VALUES
(1, 'Desenvolvimento Web',  'Criação de sites e sistemas web',  1500.00, 'Desenvolvimento'),
(1, 'Manutenção de Sites',  'Atualizações e correções',          300.00, 'Manutenção'),
(2, 'Design de Logotipo',   'Criação de identidade visual',       500.00, 'Design'),
(2, 'Marketing Digital',    'Gestão de redes sociais',           1000.00, 'Marketing'),
(3, 'Suporte Remoto',       'Atendimento técnico remoto',         200.00, 'Suporte');

INSERT INTO orcamentos (cliente_id, servico_id, empresa_id, quantidade, valor_total, status, data_orcamento) VALUES
(1, 1, 1, 1, 1500.00, 'aprovado',  CURDATE()),
(2, 3, 2, 1,  500.00, 'pendente',  CURDATE()),
(1, 5, 3, 2,  400.00, 'concluido', CURDATE());

-- Avaliação de exemplo para o orçamento concluído
INSERT INTO avaliacoes (orcamento_id, cliente_id, empresa_id, nota, titulo, comentario) VALUES
(3, 1, 3, 5, 'Excelente atendimento!', 'O suporte foi rápido e eficiente. Recomendo!');

-- Colunas para recuperação de senha
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS reset_expira DATETIME NULL;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS reset_expira DATETIME NULL;

-- ══════════════════════════════════════════
--  SISTEMA DE CHAT  (novo)
-- ══════════════════════════════════════════

-- Uma conversa vincula um cliente a uma empresa (e opcionalmente a um orçamento)
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

-- Dados de exemplo
INSERT IGNORE INTO conversas (cliente_id, empresa_id, orcamento_id) VALUES (1, 3, 3);
INSERT IGNORE INTO mensagens (conversa_id, remetente, conteudo) VALUES
  (1, 'cliente',  'Olá! Queria saber mais detalhes sobre o suporte remoto.'),
  (1, 'empresa',  'Olá João! O suporte remoto inclui atendimento via TeamViewer. Posso agendar para quando precisar.'),
  (1, 'cliente',  'Ótimo! O serviço já foi concluído, ficou muito satisfeito!');

-- Tabela de status "digitando" (auto-criada via typing.php, mas aqui para deploy completo)
CREATE TABLE IF NOT EXISTS chat_typing (
    conversa_id INT NOT NULL,
    remetente   ENUM('cliente','empresa') NOT NULL,
    status      TINYINT DEFAULT 0,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (conversa_id, remetente)
) ENGINE=InnoDB;
