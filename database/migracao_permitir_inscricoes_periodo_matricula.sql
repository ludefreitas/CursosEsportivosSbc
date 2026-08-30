ALTER TABLE temporadas
    ADD COLUMN IF NOT EXISTS permitir_inscricao_periodo_matricula TINYINT(1) NOT NULL DEFAULT 0 AFTER matriculas_fim;

ALTER TABLE cronogramas_modalidade
    ADD COLUMN IF NOT EXISTS permitir_inscricao_periodo_matricula TINYINT(1) NOT NULL DEFAULT 0 AFTER matriculas_fim;
