CREATE TABLE IF NOT EXISTS turmas_externas_migracao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    temporada_id_externa BIGINT UNSIGNED NOT NULL,
    turma_id_externa BIGINT UNSIGNED NOT NULL,
    turma_nome VARCHAR(180) NOT NULL,
    modalidade_id_externa BIGINT UNSIGNED NOT NULL,
    modalidade_nome VARCHAR(150) NOT NULL,
    local_id_externo BIGINT UNSIGNED NULL,
    local_nome VARCHAR(150) NULL,
    local_apelido VARCHAR(100) NULL,
    espaco_id_externo BIGINT UNSIGNED NULL,
    espaco_nome VARCHAR(150) NULL,
    dados_json LONGTEXT NOT NULL,
    importado_em DATETIME NOT NULL,
    UNIQUE KEY uk_turma_externa_temporada (temporada_id_externa, turma_id_externa),
    INDEX idx_turma_externa_filtros (temporada_id_externa, modalidade_id_externa, local_id_externo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
