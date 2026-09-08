ALTER TABLE modalidade_popups
    ADD COLUMN IF NOT EXISTS local_treino_id BIGINT UNSIGNED NULL AFTER modalidade_id;

-- O novo índice deve ser criado antes da remoção do índice único antigo.
-- O MySQL pode estar usando o índice antigo para sustentar a FK de modalidade_id.
SET @sql_add_popup_scope_index = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE modalidade_popups ADD INDEX idx_modalidade_popup_escopo (modalidade_id, area, local_treino_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'modalidade_popups'
      AND index_name = 'idx_modalidade_popup_escopo'
);
PREPARE stmt_add_popup_scope_index FROM @sql_add_popup_scope_index;
EXECUTE stmt_add_popup_scope_index;
DEALLOCATE PREPARE stmt_add_popup_scope_index;

SET @sql_drop_popup_unique = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE modalidade_popups DROP INDEX uq_modalidade_popup_area',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'modalidade_popups'
      AND index_name = 'uq_modalidade_popup_area'
);
PREPARE stmt_drop_popup_unique FROM @sql_drop_popup_unique;
EXECUTE stmt_drop_popup_unique;
DEALLOCATE PREPARE stmt_drop_popup_unique;

SET @sql_add_popup_local_fk = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE modalidade_popups ADD CONSTRAINT fk_modalidade_popup_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id)',
        'SELECT 1'
    )
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'modalidade_popups'
      AND constraint_name = 'fk_modalidade_popup_local'
);
PREPARE stmt_add_popup_local_fk FROM @sql_add_popup_local_fk;
EXECUTE stmt_add_popup_local_fk;
DEALLOCATE PREPARE stmt_add_popup_local_fk;
