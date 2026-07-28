USE cursos_esportivos_sbc;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'espacos_treino'
      AND COLUMN_NAME = 'supervisor_espaco'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE espacos_treino ADD COLUMN supervisor_espaco BIGINT UNSIGNED NULL AFTER local_treino_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'espacos_treino'
      AND CONSTRAINT_NAME = 'fk_espaco_supervisor'
);

SET @sql := IF(
    @constraint_exists = 0,
    'ALTER TABLE espacos_treino ADD CONSTRAINT fk_espaco_supervisor FOREIGN KEY (supervisor_espaco) REFERENCES contas(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SHOW COLUMNS FROM espacos_treino LIKE 'supervisor_espaco';
