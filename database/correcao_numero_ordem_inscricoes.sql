-- Recalcula a ordem das inscrições existentes dentro de cada turma.
-- Use este arquivo quando a coluna numero_ordem já existir e houver valores
-- nulos, iguais a zero, repetidos ou fora da sequência esperada.

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

-- O MySQL Workbench pode rejeitar UPDATE com JOIN mesmo quando o WHERE usa a
-- chave primária. Desativamos o modo seguro somente nesta operação e, ao
-- terminar, restauramos exatamente o valor anterior da sessão.
SET @sql_safe_updates_anterior = @@SQL_SAFE_UPDATES;
SET SQL_SAFE_UPDATES = 0;

UPDATE inscricoes_turma destino
INNER JOIN tmp_ordem_inscricoes ordenadas
        ON ordenadas.id = destino.id
SET destino.numero_ordem = ordenadas.numero
WHERE destino.id > 0;

SET SQL_SAFE_UPDATES = @sql_safe_updates_anterior;

DROP TEMPORARY TABLE tmp_ordem_inscricoes;

-- Conferência: esta consulta não deve retornar registros.
SELECT id, turma_id, numero_ordem
FROM inscricoes_turma
WHERE numero_ordem IS NULL OR numero_ordem < 1
ORDER BY turma_id, id;
