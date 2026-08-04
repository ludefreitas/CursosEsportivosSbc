CREATE TABLE IF NOT EXISTS home_conteudos_configurados (
    chave VARCHAR(60) PRIMARY KEY,
    conteudo_json LONGTEXT NULL,
    rascunho_json LONGTEXT NULL,
    atualizado_por_conta_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_home_conteudo_conta FOREIGN KEY (atualizado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
