SET NAMES utf8mb4;

ALTER TABLE locais_treino
    ADD COLUMN IF NOT EXISTS admin_local BIGINT UNSIGNED NULL AFTER apelido_local,
    ADD COLUMN IF NOT EXISTS coord_local BIGINT UNSIGNED NULL AFTER admin_local;

SET @fk_admin_local := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locais_treino'
      AND CONSTRAINT_NAME = 'fk_locais_treino_admin_local'
);
SET @sql_admin_local := IF(
    @fk_admin_local = 0,
    'ALTER TABLE locais_treino ADD CONSTRAINT fk_locais_treino_admin_local FOREIGN KEY (admin_local) REFERENCES contas(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_admin_local FROM @sql_admin_local;
EXECUTE stmt_admin_local;
DEALLOCATE PREPARE stmt_admin_local;

SET @fk_coord_local := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locais_treino'
      AND CONSTRAINT_NAME = 'fk_locais_treino_coord_local'
);
SET @sql_coord_local := IF(
    @fk_coord_local = 0,
    'ALTER TABLE locais_treino ADD CONSTRAINT fk_locais_treino_coord_local FOREIGN KEY (coord_local) REFERENCES contas(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_coord_local FROM @sql_coord_local;
EXECUTE stmt_coord_local;
DEALLOCATE PREPARE stmt_coord_local;
