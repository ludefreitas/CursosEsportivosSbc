UPDATE temporadas
SET status = 'suspensa'
WHERE ativo = 0
  AND id > 0
  AND status NOT IN ('encerrada', 'cancelada');

ALTER TABLE temporadas
    DROP COLUMN IF EXISTS ativo;
