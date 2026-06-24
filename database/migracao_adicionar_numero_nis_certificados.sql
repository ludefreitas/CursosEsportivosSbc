SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'certificados_pessoa'
      AND COLUMN_NAME = 'numero_nis'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE certificados_pessoa ADD COLUMN numero_nis VARCHAR(30) NULL AFTER tipos_deficiencia_pcd',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SHOW COLUMNS FROM certificados_pessoa LIKE 'numero_nis';
