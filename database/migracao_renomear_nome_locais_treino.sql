SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS backup_locais_treino_nomes (
    local_treino_id BIGINT UNSIGNED PRIMARY KEY,
    nome_anterior VARCHAR(150) NOT NULL,
    copiado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO backup_locais_treino_nomes (local_treino_id, nome_anterior)
SELECT id, nome
FROM locais_treino
ON DUPLICATE KEY UPDATE
    nome_anterior = VALUES(nome_anterior),
    copiado_em = CURRENT_TIMESTAMP;

ALTER TABLE locais_treino
    CHANGE COLUMN nome nome_local VARCHAR(150) NOT NULL,
    ADD COLUMN apelido_local VARCHAR(100) NULL AFTER nome_local;

UPDATE locais_treino
SET apelido_local = 'Baetão'
WHERE slug = 'centro-esportivo-baeta'
  AND (apelido_local IS NULL OR TRIM(apelido_local) = '');
