ALTER TABLE temporadas
    ADD COLUMN IF NOT EXISTS abrangencia_semanal VARCHAR(30) NOT NULL DEFAULT 'segunda_sexta' AFTER origem_temporada_id;

ALTER TABLE cronogramas_modalidade
    ADD COLUMN IF NOT EXISTS abrangencia_semanal VARCHAR(30) NOT NULL DEFAULT 'segunda_sexta' AFTER nome;

