-- Normaliza a instituição gestora das temporadas em uma tabela própria.
-- Preserva o campo textual legado para compatibilidade com integrações existentes.

CREATE TABLE IF NOT EXISTS origens_temporada (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_origem_temporada_nome (nome)
) ENGINE=InnoDB;

INSERT IGNORE INTO origens_temporada (nome, ativo)
VALUES ('Secretaria de Esportes e Lazer de São Bernardo do Campo', 1);

INSERT IGNORE INTO origens_temporada (nome, ativo)
SELECT DISTINCT TRIM(origem_temporada), 1
FROM temporadas
WHERE origem_temporada IS NOT NULL
  AND TRIM(origem_temporada) <> '';

ALTER TABLE temporadas
    ADD COLUMN origem_temporada_id BIGINT UNSIGNED NULL AFTER origem_temporada;

UPDATE temporadas te
INNER JOIN origens_temporada ot ON ot.nome = TRIM(te.origem_temporada)
SET te.origem_temporada_id = ot.id
WHERE te.origem_temporada_id IS NULL;

ALTER TABLE temporadas
    ADD CONSTRAINT fk_temporada_origem
    FOREIGN KEY (origem_temporada_id) REFERENCES origens_temporada(id);
