-- Vincula cada turma à conta do professor responsável.

ALTER TABLE turmas
    ADD COLUMN professor_conta_id BIGINT UNSIGNED NULL AFTER nivel_modalidade_id,
    ADD INDEX idx_turmas_professor (professor_conta_id),
    ADD CONSTRAINT fk_turmas_professor FOREIGN KEY (professor_conta_id) REFERENCES contas(id) ON DELETE SET NULL;
