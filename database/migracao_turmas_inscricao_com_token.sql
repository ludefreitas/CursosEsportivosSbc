ALTER TABLE turmas
    ADD COLUMN exigir_token_inscricao TINYINT(1) NOT NULL DEFAULT 0 AFTER regra_atestado_dermatologico;
