USE cursos_esportivos_sbc;

CREATE TABLE IF NOT EXISTS suspensoes_espaco_treino (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    espaco_treino_id BIGINT UNSIGNED NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    motivo VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_suspensoes_espaco_treino (espaco_treino_id, data_inicio, data_fim, ativo),
    CONSTRAINT fk_suspensao_espaco FOREIGN KEY (espaco_treino_id) REFERENCES espacos_treino(id)
) ENGINE=InnoDB;

SHOW TABLES LIKE 'suspensoes_espaco_treino';
