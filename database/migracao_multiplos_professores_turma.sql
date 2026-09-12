-- Permite atribuir mais de um professor a cada turma, preservando as atribuições atuais.
CREATE TABLE IF NOT EXISTS turmas_professores (
    turma_id BIGINT UNSIGNED NOT NULL,
    professor_conta_id BIGINT UNSIGNED NOT NULL,
    atribuido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (turma_id, professor_conta_id),
    INDEX idx_turmas_professores_professor (professor_conta_id),
    CONSTRAINT fk_turmas_professores_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_turmas_professores_conta FOREIGN KEY (professor_conta_id) REFERENCES contas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO turmas_professores (turma_id, professor_conta_id)
SELECT id, professor_conta_id
FROM turmas
WHERE professor_conta_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS turmas_estagiarios (
    turma_id BIGINT UNSIGNED NOT NULL,
    estagiario_conta_id BIGINT UNSIGNED NOT NULL,
    atribuido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (turma_id, estagiario_conta_id),
    INDEX idx_turmas_estagiarios_estagiario (estagiario_conta_id),
    CONSTRAINT fk_turmas_estagiarios_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_turmas_estagiarios_conta FOREIGN KEY (estagiario_conta_id) REFERENCES contas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
