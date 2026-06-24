SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pessoas'
      AND COLUMN_NAME = 'numero_cartao_sus'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE pessoas ADD COLUMN numero_cartao_sus CHAR(16) NULL AFTER telefone_whatsapp',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SHOW COLUMNS FROM pessoas LIKE 'numero_cartao_sus';
