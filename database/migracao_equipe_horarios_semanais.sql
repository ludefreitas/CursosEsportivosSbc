ALTER TABLE horarios_semanais
    ADD COLUMN professor_conta_id BIGINT UNSIGNED NULL AFTER criado_por_conta_id,
    ADD INDEX idx_horarios_semanais_professor (professor_conta_id),
    ADD CONSTRAINT fk_horario_professor FOREIGN KEY (professor_conta_id) REFERENCES contas(id) ON DELETE SET NULL;

UPDATE horarios_semanais
SET professor_conta_id = criado_por_conta_id
WHERE professor_conta_id IS NULL;

CREATE TABLE horarios_semanais_professores (
    horario_semanal_id BIGINT UNSIGNED NOT NULL,
    professor_conta_id BIGINT UNSIGNED NOT NULL,
    atribuido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (horario_semanal_id, professor_conta_id),
    CONSTRAINT fk_hsp_horario FOREIGN KEY (horario_semanal_id) REFERENCES horarios_semanais(id) ON DELETE CASCADE,
    CONSTRAINT fk_hsp_professor FOREIGN KEY (professor_conta_id) REFERENCES contas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO horarios_semanais_professores (horario_semanal_id, professor_conta_id)
SELECT id, professor_conta_id FROM horarios_semanais WHERE professor_conta_id IS NOT NULL;

CREATE TABLE horarios_semanais_estagiarios (
    horario_semanal_id BIGINT UNSIGNED NOT NULL,
    estagiario_conta_id BIGINT UNSIGNED NOT NULL,
    atribuido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (horario_semanal_id, estagiario_conta_id),
    CONSTRAINT fk_hse_horario FOREIGN KEY (horario_semanal_id) REFERENCES horarios_semanais(id) ON DELETE CASCADE,
    CONSTRAINT fk_hse_estagiario FOREIGN KEY (estagiario_conta_id) REFERENCES contas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
