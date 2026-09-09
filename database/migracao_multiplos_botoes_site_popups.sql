SET @schema_atual = DATABASE();
SET @coluna_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_atual AND TABLE_NAME = 'site_popups' AND COLUMN_NAME = 'acoes_json'
);
SET @sql = IF(
    @coluna_existe = 0,
    'ALTER TABLE site_popups ADD COLUMN acoes_json TEXT NULL AFTER url_acao',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Os registros antigos continuam sendo lidos pelos campos rotulo_acao e url_acao.
-- Ao serem editados, passam a gravar também a lista em acoes_json.
