ALTER TABLE suspensoes_espaco_treino
    ADD COLUMN IF NOT EXISTS criado_por_conta_id BIGINT UNSIGNED NULL AFTER espaco_treino_id;

UPDATE suspensoes_espaco_treino s
INNER JOIN (
    SELECT entidade_id, MAX(conta_id) AS conta_id
    FROM logs_auditoria
    WHERE tipo_evento = 'admin.suspensao_espaco_criada'
      AND tipo_entidade = 'suspensoes_espaco_treino'
      AND conta_id IS NOT NULL
    GROUP BY entidade_id
) log_criacao ON log_criacao.entidade_id = s.id
SET s.criado_por_conta_id = log_criacao.conta_id
WHERE s.criado_por_conta_id IS NULL;

SET @fk_criador_suspensao := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suspensoes_espaco_treino'
      AND CONSTRAINT_NAME = 'fk_suspensao_espaco_criador'
);
SET @sql_fk_criador_suspensao := IF(
    @fk_criador_suspensao = 0,
    'ALTER TABLE suspensoes_espaco_treino ADD CONSTRAINT fk_suspensao_espaco_criador FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_fk_criador_suspensao FROM @sql_fk_criador_suspensao;
EXECUTE stmt_fk_criador_suspensao;
DEALLOCATE PREPARE stmt_fk_criador_suspensao;
