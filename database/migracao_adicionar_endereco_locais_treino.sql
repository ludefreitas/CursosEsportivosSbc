SET NAMES utf8mb4;

ALTER TABLE locais_treino
    ADD COLUMN IF NOT EXISTS cep CHAR(8) NULL AFTER slug,
    ADD COLUMN IF NOT EXISTS logradouro VARCHAR(180) NULL AFTER cep,
    ADD COLUMN IF NOT EXISTS bairro VARCHAR(120) NULL AFTER logradouro;

UPDATE locais_treino
SET logradouro = endereco_completo
WHERE (logradouro IS NULL OR TRIM(logradouro) = '')
  AND TRIM(endereco_completo) <> '';

CREATE INDEX IF NOT EXISTS idx_locais_treino_cep
    ON locais_treino (cep);
