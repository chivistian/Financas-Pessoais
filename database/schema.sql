-- =============================================================
--  Sistema de Gestão de Finanças Pessoais - Esquema do banco
--  Baseado no MER: usuario, conta, categoria, lancamento
-- =============================================================

CREATE DATABASE IF NOT EXISTS financas_pessoais
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE financas_pessoais;

CREATE TABLE IF NOT EXISTS usuario (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL,
  senha_hash  VARCHAR(255) NOT NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_usuario_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- usuario 1 --- 0..N conta  (possui)
CREATE TABLE IF NOT EXISTS conta (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT          NOT NULL,
  nome          VARCHAR(60)  NOT NULL,
  tipo          ENUM('carteira','corrente','poupanca') NOT NULL DEFAULT 'carteira',
  saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  saldo_atual   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_conta_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- usuario 1 --- 0..N categoria  (cria)
CREATE TABLE IF NOT EXISTS categoria (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT          NOT NULL,
  nome        VARCHAR(60)  NOT NULL,
  tipo        ENUM('R','D') NOT NULL,
  CONSTRAINT uk_categoria UNIQUE (usuario_id, nome, tipo),
  CONSTRAINT fk_categoria_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- conta 1 --- 0..N lancamento (recebe) | categoria 1 --- 0..N lancamento (classifica)
CREATE TABLE IF NOT EXISTS lancamento (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  conta_id      INT           NOT NULL,
  categoria_id  INT           NOT NULL,
  tipo          ENUM('R','D') NOT NULL,
  valor         DECIMAL(12,2) NOT NULL,
  `data`        DATE          NOT NULL,
  descricao     VARCHAR(255)  NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lancamento_data (`data`),
  CONSTRAINT fk_lancamento_conta FOREIGN KEY (conta_id)
    REFERENCES conta(id) ON DELETE RESTRICT,
  CONSTRAINT fk_lancamento_categoria FOREIGN KEY (categoria_id)
    REFERENCES categoria(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
