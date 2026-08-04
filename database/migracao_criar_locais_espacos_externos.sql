CREATE TABLE IF NOT EXISTS migracoes_fontes_externas (
    chave VARCHAR(80) PRIMARY KEY,
    concluida_em DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locais_externos_migracao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_externo BIGINT UNSIGNED NOT NULL,
    apelido_local VARCHAR(100) NULL,
    nome_local VARCHAR(150) NOT NULL,
    logradouro VARCHAR(180) NULL,
    numero_endereco VARCHAR(20) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    telefone VARCHAR(30) NULL,
    cep CHAR(8) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    importado_em DATETIME NOT NULL,
    UNIQUE KEY uk_local_externo (id_externo),
    INDEX idx_local_externo_nome (nome_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locais_externos_vinculos (
    id_externo BIGINT UNSIGNED PRIMARY KEY,
    local_treino_id BIGINT UNSIGNED NOT NULL,
    vinculado_em DATETIME NOT NULL,
    UNIQUE KEY uk_local_externo_vinculo_atual (local_treino_id),
    CONSTRAINT fk_local_externo_vinculo_atual FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS espacos_externos_migracao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_externo BIGINT UNSIGNED NOT NULL,
    local_id_externo BIGINT UNSIGNED NOT NULL,
    nome_espaco VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    observacao VARCHAR(255) NULL,
    area_espaco DECIMAL(12,2) NULL,
    nome_local VARCHAR(150) NOT NULL,
    apelido_local VARCHAR(100) NULL,
    importado_em DATETIME NOT NULL,
    UNIQUE KEY uk_espaco_externo (id_externo),
    INDEX idx_espaco_externo_nome (nome_espaco),
    INDEX idx_espaco_externo_local (nome_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
