-- ============================================
-- SISTEMA DE GESTÃO PARA PAPELARIA
-- Banco de Dados MySQL
-- ============================================

CREATE DATABASE IF NOT EXISTS papelaria_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE papelaria_system;

-- ============================================
-- TABELA: usuarios
-- ============================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    foto_perfil VARCHAR(255),
    nivel_acesso ENUM('admin', 'usuario') DEFAULT 'usuario',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso TIMESTAMP NULL,
    ativo BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: clientes
-- ============================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cpf_cnpj VARCHAR(20),
    email VARCHAR(100),
    telefone VARCHAR(20) NOT NULL,
    whatsapp VARCHAR(20),
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    data_nascimento DATE,
    observacoes TEXT,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_compra TIMESTAMP NULL,
    total_compras DECIMAL(10, 2) DEFAULT 0.00,
    quantidade_pedidos INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    INDEX idx_nome (nome),
    INDEX idx_telefone (telefone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: categorias
-- ============================================
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    icone VARCHAR(50),
    cor VARCHAR(20),
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: produtos
-- ============================================
CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    codigo_sku VARCHAR(50) UNIQUE,
    preco_custo DECIMAL(10, 2) DEFAULT 0.00,
    preco_venda DECIMAL(10, 2) NOT NULL,
    margem_lucro DECIMAL(5, 2),
    estoque_atual INT DEFAULT 0,
    estoque_minimo INT DEFAULT 5,
    unidade_medida VARCHAR(20) DEFAULT 'un',
    imagem VARCHAR(255),
    ativo BOOLEAN DEFAULT TRUE,
    destaque BOOLEAN DEFAULT FALSE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
    INDEX idx_nome (nome),
    INDEX idx_categoria (categoria_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: pedidos
-- ============================================
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_pedido VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INT NOT NULL,
    usuario_id INT,
    valor_total DECIMAL(10, 2) NOT NULL,
    desconto DECIMAL(10, 2) DEFAULT 0.00,
    valor_final DECIMAL(10, 2) NOT NULL,
    status ENUM('aguardando', 'em_producao', 'pronto', 'entregue', 'cancelado') DEFAULT 'aguardando',
    forma_pagamento ENUM('dinheiro', 'pix', 'credito', 'debito', 'boleto', 'outro') DEFAULT 'dinheiro',
    status_pagamento ENUM('pendente', 'pago', 'parcial') DEFAULT 'pendente',
    valor_pago DECIMAL(10, 2) DEFAULT 0.00,
    data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_entrega DATE,
    data_entrega_realizada TIMESTAMP NULL,
    observacoes TEXT,
    observacoes_internas TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_numero (numero_pedido),
    INDEX idx_cliente (cliente_id),
    INDEX idx_status (status),
    INDEX idx_data_pedido (data_pedido),
    INDEX idx_data_entrega (data_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: pedidos_itens
-- ============================================
CREATE TABLE IF NOT EXISTS pedidos_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    personalizacao TEXT,
    observacoes TEXT,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    INDEX idx_pedido (pedido_id),
    INDEX idx_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: movimentacoes_estoque
-- ============================================
CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    tipo ENUM('entrada', 'saida', 'ajuste', 'devolucao') NOT NULL,
    quantidade INT NOT NULL,
    estoque_anterior INT NOT NULL,
    estoque_atual INT NOT NULL,
    motivo VARCHAR(200),
    referencia_id INT,
    referencia_tipo VARCHAR(50),
    usuario_id INT,
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_produto (produto_id),
    INDEX idx_data (data_movimentacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: financeiro
-- ============================================
CREATE TABLE IF NOT EXISTS financeiro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('receita', 'despesa') NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10, 2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    status ENUM('pendente', 'pago', 'vencido') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),
    pedido_id INT,
    cliente_id INT,
    usuario_id INT,
    observacoes TEXT,
    arquivo_comprovante VARCHAR(255),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_tipo (tipo),
    INDEX idx_status (status),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_data_pagamento (data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: configuracoes
-- ============================================
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    tipo ENUM('texto', 'numero', 'boolean', 'json') DEFAULT 'texto',
    descricao VARCHAR(255),
    grupo VARCHAR(50),
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELA: log_atividades
-- ============================================
CREATE TABLE IF NOT EXISTS log_atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    acao VARCHAR(100) NOT NULL,
    tabela VARCHAR(50),
    registro_id INT,
    detalhes TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_data (data_hora),
    INDEX idx_tabela_registro (tabela, registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- DADOS INICIAIS
-- ============================================

-- Inserir usuário admin padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES
('Administrador', 'admin@papelaria.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Inserir categorias padrão
INSERT INTO categorias (nome, descricao, icone, cor, ordem) VALUES
('Cadernos', 'Cadernos personalizados diversos tamanhos', '📓', '#8B5CF6', 1),
('Agendas', 'Agendas personalizadas', '📅', '#EC4899', 2),
('Planners', 'Planners semanais e mensais', '📋', '#3B82F6', 3),
('Kits', 'Kits personalizados para presentes', '🎁', '#10B981', 4),
('Canetas', 'Canetas personalizadas', '🖊️', '#F59E0B', 5),
('Blocos', 'Blocos de anotações', '📝', '#6366F1', 6),
('Convites', 'Convites personalizados', '💌', '#EF4444', 7),
('Adesivos', 'Adesivos personalizados', '✨', '#06B6D4', 8);

-- Inserir produtos de exemplo
INSERT INTO produtos (categoria_id, nome, descricao, preco_custo, preco_venda, estoque_atual, estoque_minimo) VALUES
(1, 'Caderno Personalizado A5', 'Caderno capa dura personalizado tamanho A5 com 96 folhas', 25.00, 45.00, 25, 10),
(1, 'Caderno Universitário', 'Caderno universitário 10 matérias personalizado', 18.00, 35.00, 30, 10),
(2, 'Agenda 2025 Luxo', 'Agenda executiva 2025 capa dura', 45.00, 89.90, 15, 5),
(2, 'Agenda Compacta', 'Agenda de bolso personalizada', 20.00, 40.00, 20, 8),
(3, 'Planner Semanal', 'Planner semanal A5', 30.00, 65.00, 30, 10),
(3, 'Planner Mensal', 'Planner mensal completo', 35.00, 75.00, 20, 8),
(4, 'Kit Escritório Personalizado', 'Kit completo para escritório', 60.00, 120.00, 10, 5),
(4, 'Kit Escolar', 'Kit escolar completo', 40.00, 85.00, 15, 5),
(5, 'Caneta Especial Nome', 'Caneta com nome gravado', 8.00, 15.00, 50, 20),
(5, 'Kit 3 Canetas', 'Kit com 3 canetas personalizadas', 20.00, 38.00, 25, 10);

-- Inserir clientes de exemplo
INSERT INTO clientes (nome, telefone, email, cidade, estado, total_compras, quantidade_pedidos) VALUES
('Maria Silva', '(11) 98765-4321', 'maria@email.com', 'São Paulo', 'SP', 450.00, 5),
('João Santos', '(11) 97654-3210', 'joao@email.com', 'São Paulo', 'SP', 320.00, 4),
('Ana Costa', '(11) 96543-2109', 'ana@email.com', 'Rio de Janeiro', 'RJ', 680.00, 8),
('Pedro Oliveira', '(11) 95432-1098', 'pedro@email.com', 'Belo Horizonte', 'MG', 195.00, 2),
('Juliana Mendes', '(11) 94321-0987', 'juliana@email.com', 'Curitiba', 'PR', 280.00, 3);

-- Inserir configurações padrão
INSERT INTO configuracoes (chave, valor, tipo, descricao, grupo) VALUES
('empresa_nome', 'Minha Papelaria', 'texto', 'Nome da empresa', 'geral'),
('empresa_telefone', '(00) 0000-0000', 'texto', 'Telefone da empresa', 'geral'),
('empresa_email', 'contato@papelaria.com', 'texto', 'Email da empresa', 'geral'),
('prazo_entrega_padrao', '7', 'numero', 'Prazo padrão de entrega em dias', 'pedidos'),
('estoque_alerta', '10', 'numero', 'Quantidade mínima para alerta de estoque', 'estoque'),
('moeda', 'BRL', 'texto', 'Código da moeda', 'financeiro');

-- ============================================
-- VIEWS ÚTEIS
-- ============================================

-- View: Resumo de Produtos
CREATE OR REPLACE VIEW vw_produtos_resumo AS
SELECT 
    p.id,
    p.nome,
    c.nome as categoria,
    p.preco_venda,
    p.estoque_atual,
    p.estoque_minimo,
    CASE 
        WHEN p.estoque_atual <= p.estoque_minimo THEN 'Baixo'
        WHEN p.estoque_atual <= (p.estoque_minimo * 2) THEN 'Médio'
        ELSE 'Alto'
    END as status_estoque,
    p.ativo
FROM produtos p
INNER JOIN categorias c ON p.categoria_id = c.id;

-- View: Resumo de Pedidos
CREATE OR REPLACE VIEW vw_pedidos_resumo AS
SELECT 
    p.id,
    p.numero_pedido,
    c.nome as cliente,
    c.telefone as cliente_telefone,
    p.valor_final,
    p.status,
    p.status_pagamento,
    p.data_pedido,
    p.data_entrega,
    COUNT(pi.id) as total_itens,
    DATEDIFF(p.data_entrega, CURDATE()) as dias_ate_entrega
FROM pedidos p
INNER JOIN clientes c ON p.cliente_id = c.id
LEFT JOIN pedidos_itens pi ON p.id = pi.pedido_id
GROUP BY p.id;

-- View: Dashboard Financeiro
CREATE OR REPLACE VIEW vw_financeiro_resumo AS
SELECT 
    DATE_FORMAT(data_vencimento, '%Y-%m') as mes_ano,
    tipo,
    SUM(valor) as total,
    COUNT(*) as quantidade,
    SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as total_pago,
    SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as total_pendente
FROM financeiro
GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m'), tipo;

-- ============================================
-- TRIGGERS
-- ============================================

-- Trigger: Atualizar estoque ao adicionar item ao pedido
DELIMITER $$
CREATE TRIGGER after_pedido_item_insert
AFTER INSERT ON pedidos_itens
FOR EACH ROW
BEGIN
    DECLARE estoque_anterior INT;
    
    SELECT estoque_atual INTO estoque_anterior
    FROM produtos
    WHERE id = NEW.produto_id;
    
    UPDATE produtos 
    SET estoque_atual = estoque_atual - NEW.quantidade
    WHERE id = NEW.produto_id;
    
    INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, estoque_anterior, estoque_atual, motivo, referencia_id, referencia_tipo)
    VALUES (NEW.produto_id, 'saida', NEW.quantidade, estoque_anterior, estoque_anterior - NEW.quantidade, 'Venda - Pedido', NEW.pedido_id, 'pedido');
END$$

-- Trigger: Atualizar totais do cliente
CREATE TRIGGER after_pedido_update
AFTER UPDATE ON pedidos
FOR EACH ROW
BEGIN
    IF NEW.status = 'entregue' AND OLD.status != 'entregue' THEN
        UPDATE clientes
        SET total_compras = total_compras + NEW.valor_final,
            quantidade_pedidos = quantidade_pedidos + 1,
            ultima_compra = NEW.data_entrega_realizada
        WHERE id = NEW.cliente_id;
    END IF;
END$$

-- Trigger: Gerar número do pedido automaticamente
CREATE TRIGGER before_pedido_insert
BEFORE INSERT ON pedidos
FOR EACH ROW
BEGIN
    IF NEW.numero_pedido IS NULL OR NEW.numero_pedido = '' THEN
        SET NEW.numero_pedido = CONCAT('PED', LPAD(FLOOR(RAND() * 99999), 5, '0'));
    END IF;
END$$

DELIMITER ;

-- ============================================
-- FIM DO SCRIPT
-- ============================================
