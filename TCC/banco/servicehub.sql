-- banco/servicehub.sql
CREATE DATABASE IF NOT EXISTS servicehub;
USE servicehub;

-- Tabela de serviços
CREATE TABLE servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    duracao_estimada INT COMMENT 'Duração em horas',
    categoria VARCHAR(50),
    status TINYINT DEFAULT 1 COMMENT '1=Ativo, 0=Inativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefone VARCHAR(20),
    endereco TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de orçamentos
CREATE TABLE orcamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    data_orcamento DATE NOT NULL,
    data_validade DATE,
    valor_total DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'aprovado', 'rejeitado', 'expirado') DEFAULT 'pendente',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- Tabela de itens do orçamento
CREATE TABLE orcamento_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT NOT NULL,
    servico_id INT NOT NULL,
    quantidade INT DEFAULT 1,
    valor_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    observacoes TEXT,
    FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id)
);

-- Inserindo dados de exemplo
INSERT INTO servicos (nome, descricao, valor, duracao_estimada, categoria) VALUES
('Desenvolvimento Web', 'Criação de sites e sistemas web personalizados', 1500.00, 40, 'Desenvolvimento'),
('Manutenção de Sites', 'Atualizações e correções em sites existentes', 300.00, 8, 'Manutenção'),
('Design Gráfico', 'Criação de logotipos, banners e material visual', 500.00, 10, 'Design'),
('Consultoria TI', 'Consultoria em tecnologia da informação', 800.00, 4, 'Consultoria'),
('Marketing Digital', 'Estratégias de marketing e redes sociais', 1000.00, 20, 'Marketing'),
('Suporte Técnico', 'Suporte remoto e presencial', 200.00, 5, 'Suporte');

INSERT INTO clientes (nome, email, telefone) VALUES
('Empresa ABC Ltda', 'contato@abc.com', '(11) 99999-1111'),
('Comércio XYZ', 'comercial@xyz.com', '(11) 99999-2222'),
('João Silva', 'joao@email.com', '(11) 99999-3333'),
('Maria Santos', 'maria@email.com', '(11) 99999-4444');

INSERT INTO orcamentos (cliente_id, data_orcamento, data_validade, valor_total, status) VALUES
(1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2300.00, 'aprovado'),
(2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 500.00, 'pendente'),
(3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1500.00, 'aprovado'),
(1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 800.00, 'rejeitado');

INSERT INTO orcamento_itens (orcamento_id, servico_id, quantidade, valor_unitario, subtotal) VALUES
(1, 1, 1, 1500.00, 1500.00),
(1, 6, 4, 200.00, 800.00),
(2, 3, 1, 500.00, 500.00),
(3, 1, 1, 1500.00, 1500.00),
(4, 4, 1, 800.00, 800.00);