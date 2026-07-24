SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS backup_locais_treino_enderecos (
    local_treino_id BIGINT UNSIGNED PRIMARY KEY,
    endereco_completo VARCHAR(255) NOT NULL,
    copiado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO backup_locais_treino_enderecos (local_treino_id, endereco_completo)
SELECT id, endereco_completo
FROM locais_treino
WHERE endereco_completo IS NOT NULL
  AND TRIM(endereco_completo) <> ''
ON DUPLICATE KEY UPDATE
    endereco_completo = VALUES(endereco_completo),
    copiado_em = CURRENT_TIMESTAMP;

ALTER TABLE locais_treino
    ADD COLUMN IF NOT EXISTS cep CHAR(8) NULL AFTER slug,
    ADD COLUMN IF NOT EXISTS logradouro VARCHAR(180) NULL AFTER cep,
    ADD COLUMN IF NOT EXISTS numero_endereco VARCHAR(20) NULL AFTER logradouro,
    ADD COLUMN IF NOT EXISTS complemento VARCHAR(120) NULL AFTER numero_endereco,
    ADD COLUMN IF NOT EXISTS bairro VARCHAR(120) NULL AFTER complemento;

UPDATE locais_treino
SET
    logradouro = CASE
        WHEN SUBSTRING_INDEX(endereco_completo, ' - ', 1) LIKE '%,%'
            THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(endereco_completo, ' - ', 1), ',', 1))
        ELSE TRIM(SUBSTRING_INDEX(endereco_completo, ' - ', 1))
    END,
    numero_endereco = CASE
        WHEN SUBSTRING_INDEX(endereco_completo, ' - ', 1) LIKE '%,%'
            THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(endereco_completo, ' - ', 1), ',', -1))
        ELSE numero_endereco
    END,
    bairro = CASE
        WHEN endereco_completo LIKE '% - %'
            THEN TRIM(SUBSTRING_INDEX(endereco_completo, ' - ', -1))
        ELSE bairro
    END
WHERE endereco_completo IS NOT NULL
  AND TRIM(endereco_completo) <> '';

CREATE INDEX IF NOT EXISTS idx_locais_treino_cep
    ON locais_treino (cep);

ALTER TABLE locais_treino
    DROP COLUMN IF EXISTS endereco_completo;
