ALTER TABLE turmas
    ADD COLUMN IF NOT EXISTS excecoes_idade_json JSON NULL AFTER criterio_faixa_etaria;
