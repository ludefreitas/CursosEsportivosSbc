CREATE TABLE IF NOT EXISTS turmas_chamadas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turma_id BIGINT UNSIGNED NOT NULL,
    inscricao_turma_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    data_aula DATE NOT NULL,
    status ENUM('presente','ausente','justificado') NOT NULL,
    justificativa VARCHAR(500) NULL,
    chamada_por_conta_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_turma_chamada (inscricao_turma_id, data_aula),
    INDEX idx_turma_chamada_data (turma_id, data_aula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
