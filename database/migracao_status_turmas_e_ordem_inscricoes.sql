-- Status calculado das turmas e número sequencial das inscrições por turma.
-- Execute uma única vez, depois das migrações do sistema de inscrições e cronogramas.

ALTER TABLE turmas
    ADD COLUMN status ENUM(
        'planejada',
        'processo_inicial',
        'periodo_matricula',
        'inscricoes_abertas',
        'inscricoes_suspensas',
        'inscricoes_encerradas'
    ) NOT NULL DEFAULT 'planejada' AFTER inscricoes_abertas;

UPDATE turmas
SET status = CASE
    WHEN ativo = 0 THEN 'inscricoes_suspensas'
    ELSE 'planejada'
END;

ALTER TABLE inscricoes_turma
    ADD COLUMN numero_ordem INT UNSIGNED NULL AFTER turma_id;

-- Compatível também com MySQL 5.7 e versões do MariaDB sem ROW_NUMBER().
-- A tabela temporária evita atualizar e consultar inscricoes_turma diretamente
-- na mesma instrução.
DROP TEMPORARY TABLE IF EXISTS tmp_ordem_inscricoes;

CREATE TEMPORARY TABLE tmp_ordem_inscricoes (
    id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    numero INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_ordem_inscricoes (id, numero)
SELECT atual.id,
       COUNT(anteriores.id) AS numero
FROM inscricoes_turma atual
INNER JOIN inscricoes_turma anteriores
        ON anteriores.turma_id = atual.turma_id
       AND (
            COALESCE(anteriores.created_at, '1000-01-01 00:00:00')
                < COALESCE(atual.created_at, '1000-01-01 00:00:00')
            OR (
                COALESCE(anteriores.created_at, '1000-01-01 00:00:00')
                    = COALESCE(atual.created_at, '1000-01-01 00:00:00')
                AND anteriores.id <= atual.id
            )
       )
GROUP BY atual.id;

SET @sql_safe_updates_anterior = @@SQL_SAFE_UPDATES;
SET SQL_SAFE_UPDATES = 0;

UPDATE inscricoes_turma destino
INNER JOIN tmp_ordem_inscricoes ordenadas
        ON ordenadas.id = destino.id
SET destino.numero_ordem = ordenadas.numero
WHERE destino.id > 0;

SET SQL_SAFE_UPDATES = @sql_safe_updates_anterior;

DROP TEMPORARY TABLE tmp_ordem_inscricoes;

ALTER TABLE inscricoes_turma
    MODIFY COLUMN numero_ordem INT UNSIGNED NOT NULL,
    ADD UNIQUE INDEX uq_inscricao_numero_ordem_turma (turma_id, numero_ordem);
