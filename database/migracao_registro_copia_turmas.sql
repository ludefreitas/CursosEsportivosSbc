CREATE TABLE IF NOT EXISTS turmas_copias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origem_tipo ENUM('legacy', 'current') NOT NULL,
    origem_temporada_id BIGINT UNSIGNED NOT NULL,
    origem_turma_id BIGINT UNSIGNED NOT NULL,
    destino_temporada_id BIGINT UNSIGNED NOT NULL,
    destino_turma_id BIGINT UNSIGNED NOT NULL,
    copiado_por_conta_id BIGINT UNSIGNED NOT NULL,
    copiado_em DATETIME NOT NULL,
    INDEX idx_turma_copia_origem (origem_tipo, origem_temporada_id, origem_turma_id),
    INDEX idx_turma_copia_destino (destino_turma_id),
    CONSTRAINT fk_turma_copia_temporada FOREIGN KEY (destino_temporada_id) REFERENCES temporadas(id),
    CONSTRAINT fk_turma_copia_destino FOREIGN KEY (destino_turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_turma_copia_conta FOREIGN KEY (copiado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
