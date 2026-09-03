SET @possui_coluna_criador = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'horarios_semanais'
      AND COLUMN_NAME = 'criado_por_conta_id'
);
SET @sql_coluna_criador = IF(
    @possui_coluna_criador = 0,
    'ALTER TABLE horarios_semanais ADD COLUMN criado_por_conta_id BIGINT UNSIGNED NULL AFTER id, ADD INDEX idx_horarios_semanais_criador (criado_por_conta_id)',
    'SELECT 1'
);
PREPARE stmt_coluna_criador FROM @sql_coluna_criador;
EXECUTE stmt_coluna_criador;
DEALLOCATE PREPARE stmt_coluna_criador;

SET @possui_fk_criador = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'horarios_semanais'
      AND CONSTRAINT_NAME = 'fk_horario_criador'
);
SET @sql_fk_criador = IF(
    @possui_fk_criador = 0,
    'ALTER TABLE horarios_semanais ADD CONSTRAINT fk_horario_criador FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_fk_criador FROM @sql_fk_criador;
EXECUTE stmt_fk_criador;
DEALLOCATE PREPARE stmt_fk_criador;

UPDATE horarios_semanais hs
SET hs.criado_por_conta_id = (
    SELECT la.conta_id
    FROM logs_auditoria la
    WHERE la.tipo_entidade = 'horarios_semanais'
      AND la.entidade_id = hs.id
      AND la.tipo_evento = 'admin.horario_semanal_criado'
      AND la.conta_id IS NOT NULL
    ORDER BY la.id ASC
    LIMIT 1
)
WHERE hs.criado_por_conta_id IS NULL;

SELECT id, criado_por_conta_id, dia_semana, hora_inicio, hora_fim
FROM horarios_semanais
ORDER BY id;
