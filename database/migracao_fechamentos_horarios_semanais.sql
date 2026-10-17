-- Executar separadamente antes de publicar o recurso. Não executar durante requisições HTTP.
CREATE TABLE IF NOT EXISTS fechamentos_horario_semanal (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    horario_semanal_id BIGINT UNSIGNED NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    motivo TEXT NOT NULL,
    criado_por_conta_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fechamento_horario_datas (horario_semanal_id, data_inicio, data_fim),
    CONSTRAINT fk_fechamento_horario FOREIGN KEY (horario_semanal_id) REFERENCES horarios_semanais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
