-- Ajusta bancos existentes para o padrão oficial de 15 dígitos do Cartão SUS.
-- Antes de reduzir a coluna, preserva os valores antigos inválidos para conferência.

CREATE TABLE IF NOT EXISTS cartoes_sus_invalidos_backup (
    pessoa_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    numero_cartao_sus_original VARCHAR(255) NOT NULL,
    registrado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO cartoes_sus_invalidos_backup (pessoa_id, numero_cartao_sus_original)
SELECT id, numero_cartao_sus
FROM pessoas
WHERE numero_cartao_sus IS NOT NULL
  AND numero_cartao_sus <> ''
  AND numero_cartao_sus NOT REGEXP '^[0-9]{15}$'
ON DUPLICATE KEY UPDATE
    numero_cartao_sus_original = VALUES(numero_cartao_sus_original),
    registrado_em = CURRENT_TIMESTAMP;

UPDATE pessoas
SET numero_cartao_sus = NULL,
    updated_at = NOW()
WHERE numero_cartao_sus IS NOT NULL
  AND numero_cartao_sus <> ''
  AND numero_cartao_sus NOT REGEXP '^[0-9]{15}$';

ALTER TABLE pessoas
    MODIFY COLUMN numero_cartao_sus CHAR(15) NULL;

SET @constraint_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pessoas'
      AND CONSTRAINT_NAME = 'chk_pessoas_cartao_sus_15_digitos'
      AND CONSTRAINT_TYPE = 'CHECK'
);

SET @sql := IF(
    @constraint_exists = 0,
    'ALTER TABLE pessoas ADD CONSTRAINT chk_pessoas_cartao_sus_15_digitos CHECK (numero_cartao_sus IS NULL OR numero_cartao_sus REGEXP ''^[0-9]{15}$'')',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SHOW COLUMNS FROM pessoas LIKE 'numero_cartao_sus';

SELECT pessoa_id, numero_cartao_sus_original, registrado_em
FROM cartoes_sus_invalidos_backup
ORDER BY registrado_em DESC, pessoa_id ASC;
