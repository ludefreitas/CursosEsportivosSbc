-- Índices para as consultas hierárquicas da gestão de inscrições.
-- Execute uma única vez no banco de dados de cada ambiente.

SET @schema_name := DATABASE();

SET @has_local_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'turmas'
      AND index_name = 'idx_turmas_temporada_local_modalidade'
);
SET @local_index_sql := IF(
    @has_local_index = 0,
    'ALTER TABLE turmas ADD INDEX idx_turmas_temporada_local_modalidade (temporada_id, local_treino_id, modalidade_id, id)',
    'SELECT 1'
);
PREPARE local_index_statement FROM @local_index_sql;
EXECUTE local_index_statement;
DEALLOCATE PREPARE local_index_statement;

SET @has_modality_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'turmas'
      AND index_name = 'idx_turmas_temporada_modalidade_local'
);
SET @modality_index_sql := IF(
    @has_modality_index = 0,
    'ALTER TABLE turmas ADD INDEX idx_turmas_temporada_modalidade_local (temporada_id, modalidade_id, local_treino_id, id)',
    'SELECT 1'
);
PREPARE modality_index_statement FROM @modality_index_sql;
EXECUTE modality_index_statement;
DEALLOCATE PREPARE modality_index_statement;
