SET @tem_tabela_antiga := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_eventos_especiais'
);

SET @tem_tabela_nova := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
);

SET @sql_renomear_horarios := IF(
    @tem_tabela_antiga > 0 AND @tem_tabela_nova = 0,
    'RENAME TABLE agenda_eventos_especiais TO agenda_horarios_especiais',
    'SELECT 1'
);
PREPARE stmt FROM @sql_renomear_horarios;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tem_inscricoes_antiga := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_eventos_especiais_inscricoes'
);

SET @tem_inscricoes_nova := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
);

SET @sql_renomear_inscricoes := IF(
    @tem_inscricoes_antiga > 0 AND @tem_inscricoes_nova = 0,
    'RENAME TABLE agenda_eventos_especiais_inscricoes TO agenda_horarios_especiais_inscricoes',
    'SELECT 1'
);
PREPARE stmt FROM @sql_renomear_inscricoes;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE agenda_horarios_especiais
    ADD COLUMN IF NOT EXISTS criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata' AFTER idade_maxima,
    ADD COLUMN IF NOT EXISTS vagas_geral INT NOT NULL DEFAULT 0 AFTER criterio_faixa_etaria,
    ADD COLUMN IF NOT EXISTS vagas_pcd INT NOT NULL DEFAULT 0 AFTER vagas_geral,
    ADD COLUMN IF NOT EXISTS vagas_plm INT NOT NULL DEFAULT 0 AFTER vagas_pcd,
    ADD COLUMN IF NOT EXISTS vagas_pvs INT NOT NULL DEFAULT 0 AFTER vagas_plm;

SET @coluna_antiga_inscricao := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND column_name = 'agenda_evento_especial_id'
);

SET @coluna_nova_inscricao := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND column_name = 'agenda_horario_especial_id'
);

SET @sql_renomear_coluna_inscricao := IF(
    @coluna_antiga_inscricao > 0 AND @coluna_nova_inscricao = 0,
    'ALTER TABLE agenda_horarios_especiais_inscricoes CHANGE COLUMN agenda_evento_especial_id agenda_horario_especial_id BIGINT UNSIGNED NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_renomear_coluna_inscricao;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE agenda_horarios_especiais_inscricoes
    ADD COLUMN IF NOT EXISTS publico_alvo ENUM('geral', 'pcd', 'plm', 'pvs') NOT NULL DEFAULT 'geral' AFTER data_nascimento;

SET @fk_local_antiga := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND constraint_name = 'fk_agenda_evento_especial_local'
);
SET @sql_drop_fk_local_antiga := IF(@fk_local_antiga > 0, 'ALTER TABLE agenda_horarios_especiais DROP FOREIGN KEY fk_agenda_evento_especial_local', 'SELECT 1');
PREPARE stmt FROM @sql_drop_fk_local_antiga;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_espaco_antiga := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND constraint_name = 'fk_agenda_evento_especial_espaco'
);
SET @sql_drop_fk_espaco_antiga := IF(@fk_espaco_antiga > 0, 'ALTER TABLE agenda_horarios_especiais DROP FOREIGN KEY fk_agenda_evento_especial_espaco', 'SELECT 1');
PREPARE stmt FROM @sql_drop_fk_espaco_antiga;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_modalidade_antiga := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND constraint_name = 'fk_agenda_evento_especial_modalidade'
);
SET @sql_drop_fk_modalidade_antiga := IF(@fk_modalidade_antiga > 0, 'ALTER TABLE agenda_horarios_especiais DROP FOREIGN KEY fk_agenda_evento_especial_modalidade', 'SELECT 1');
PREPARE stmt FROM @sql_drop_fk_modalidade_antiga;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_local_nova := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND constraint_name = 'fk_agenda_horario_especial_local'
);
SET @sql_add_fk_local_nova := IF(@fk_local_nova = 0, 'ALTER TABLE agenda_horarios_especiais ADD CONSTRAINT fk_agenda_horario_especial_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id)', 'SELECT 1');
PREPARE stmt FROM @sql_add_fk_local_nova;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_espaco_nova := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND constraint_name = 'fk_agenda_horario_especial_espaco'
);
SET @sql_add_fk_espaco_nova := IF(@fk_espaco_nova = 0, 'ALTER TABLE agenda_horarios_especiais ADD CONSTRAINT fk_agenda_horario_especial_espaco FOREIGN KEY (espaco_treino_id) REFERENCES espacos_treino(id)', 'SELECT 1');
PREPARE stmt FROM @sql_add_fk_espaco_nova;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_modalidade_nova := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND constraint_name = 'fk_agenda_horario_especial_modalidade'
);
SET @sql_add_fk_modalidade_nova := IF(@fk_modalidade_nova = 0, 'ALTER TABLE agenda_horarios_especiais ADD CONSTRAINT fk_agenda_horario_especial_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)', 'SELECT 1');
PREPARE stmt FROM @sql_add_fk_modalidade_nova;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_inscricao_horario_antiga := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND constraint_name = 'fk_agenda_evento_especial_inscricao_evento'
);
SET @sql_drop_fk_inscricao_horario_antiga := IF(@fk_inscricao_horario_antiga > 0, 'ALTER TABLE agenda_horarios_especiais_inscricoes DROP FOREIGN KEY fk_agenda_evento_especial_inscricao_evento', 'SELECT 1');
PREPARE stmt FROM @sql_drop_fk_inscricao_horario_antiga;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_inscricao_pessoa_antiga := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND constraint_name = 'fk_agenda_evento_especial_inscricao_pessoa'
);
SET @sql_drop_fk_inscricao_pessoa_antiga := IF(@fk_inscricao_pessoa_antiga > 0, 'ALTER TABLE agenda_horarios_especiais_inscricoes DROP FOREIGN KEY fk_agenda_evento_especial_inscricao_pessoa', 'SELECT 1');
PREPARE stmt FROM @sql_drop_fk_inscricao_pessoa_antiga;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_inscricao_conta_antiga := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND constraint_name = 'fk_agenda_evento_especial_inscricao_conta'
);
SET @sql_drop_fk_inscricao_conta_antiga := IF(@fk_inscricao_conta_antiga > 0, 'ALTER TABLE agenda_horarios_especiais_inscricoes DROP FOREIGN KEY fk_agenda_evento_especial_inscricao_conta', 'SELECT 1');
PREPARE stmt FROM @sql_drop_fk_inscricao_conta_antiga;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_inscricao_horario_nova := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND constraint_name = 'fk_agenda_horario_especial_inscricao_horario'
);
SET @sql_add_fk_inscricao_horario_nova := IF(@fk_inscricao_horario_nova = 0, 'ALTER TABLE agenda_horarios_especiais_inscricoes ADD CONSTRAINT fk_agenda_horario_especial_inscricao_horario FOREIGN KEY (agenda_horario_especial_id) REFERENCES agenda_horarios_especiais(id)', 'SELECT 1');
PREPARE stmt FROM @sql_add_fk_inscricao_horario_nova;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_inscricao_pessoa_nova := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND constraint_name = 'fk_agenda_horario_especial_inscricao_pessoa'
);
SET @sql_add_fk_inscricao_pessoa_nova := IF(@fk_inscricao_pessoa_nova = 0, 'ALTER TABLE agenda_horarios_especiais_inscricoes ADD CONSTRAINT fk_agenda_horario_especial_inscricao_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)', 'SELECT 1');
PREPARE stmt FROM @sql_add_fk_inscricao_pessoa_nova;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_inscricao_conta_nova := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND constraint_name = 'fk_agenda_horario_especial_inscricao_conta'
);
SET @sql_add_fk_inscricao_conta_nova := IF(@fk_inscricao_conta_nova = 0, 'ALTER TABLE agenda_horarios_especiais_inscricoes ADD CONSTRAINT fk_agenda_horario_especial_inscricao_conta FOREIGN KEY (conta_id) REFERENCES contas(id)', 'SELECT 1');
PREPARE stmt FROM @sql_add_fk_inscricao_conta_nova;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_periodo_antigo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND index_name = 'idx_agenda_eventos_especiais_periodo'
);
SET @sql_drop_idx_periodo_antigo := IF(@idx_periodo_antigo > 0, 'DROP INDEX idx_agenda_eventos_especiais_periodo ON agenda_horarios_especiais', 'SELECT 1');
PREPARE stmt FROM @sql_drop_idx_periodo_antigo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_publicacao_antigo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND index_name = 'idx_agenda_eventos_especiais_publicacao'
);
SET @sql_drop_idx_publicacao_antigo := IF(@idx_publicacao_antigo > 0, 'DROP INDEX idx_agenda_eventos_especiais_publicacao ON agenda_horarios_especiais', 'SELECT 1');
PREPARE stmt FROM @sql_drop_idx_publicacao_antigo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_periodo_novo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND index_name = 'idx_agenda_horarios_especiais_periodo'
);
SET @sql_add_idx_periodo_novo := IF(@idx_periodo_novo = 0, 'CREATE INDEX idx_agenda_horarios_especiais_periodo ON agenda_horarios_especiais (data_inicio, data_fim, ativo)', 'SELECT 1');
PREPARE stmt FROM @sql_add_idx_periodo_novo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_publicacao_novo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais'
      AND index_name = 'idx_agenda_horarios_especiais_publicacao'
);
SET @sql_add_idx_publicacao_novo := IF(@idx_publicacao_novo = 0, 'CREATE INDEX idx_agenda_horarios_especiais_publicacao ON agenda_horarios_especiais (data_publicacao_inicio, data_publicacao_fim, ativo)', 'SELECT 1');
PREPARE stmt FROM @sql_add_idx_publicacao_novo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_inscricao_horario_antigo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND index_name = 'idx_agenda_eventos_especiais_inscricao_evento'
);
SET @sql_drop_idx_inscricao_horario_antigo := IF(@idx_inscricao_horario_antigo > 0, 'DROP INDEX idx_agenda_eventos_especiais_inscricao_evento ON agenda_horarios_especiais_inscricoes', 'SELECT 1');
PREPARE stmt FROM @sql_drop_idx_inscricao_horario_antigo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_inscricao_cpf_antigo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND index_name = 'idx_agenda_eventos_especiais_inscricao_cpf'
);
SET @sql_drop_idx_inscricao_cpf_antigo := IF(@idx_inscricao_cpf_antigo > 0, 'DROP INDEX idx_agenda_eventos_especiais_inscricao_cpf ON agenda_horarios_especiais_inscricoes', 'SELECT 1');
PREPARE stmt FROM @sql_drop_idx_inscricao_cpf_antigo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_inscricao_horario_novo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND index_name = 'idx_agenda_horarios_especiais_inscricao_horario'
);
SET @sql_add_idx_inscricao_horario_novo := IF(@idx_inscricao_horario_novo = 0, 'CREATE INDEX idx_agenda_horarios_especiais_inscricao_horario ON agenda_horarios_especiais_inscricoes (agenda_horario_especial_id, status)', 'SELECT 1');
PREPARE stmt FROM @sql_add_idx_inscricao_horario_novo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_inscricao_cpf_novo := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agenda_horarios_especiais_inscricoes'
      AND index_name = 'idx_agenda_horarios_especiais_inscricao_cpf'
);
SET @sql_add_idx_inscricao_cpf_novo := IF(@idx_inscricao_cpf_novo = 0, 'CREATE INDEX idx_agenda_horarios_especiais_inscricao_cpf ON agenda_horarios_especiais_inscricoes (cpf, status)', 'SELECT 1');
PREPARE stmt FROM @sql_add_idx_inscricao_cpf_novo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE agenda_horarios_especiais
SET
    descricao = REPLACE(REPLACE(descricao, 'Evento especial', 'Horario especial'), 'evento especial', 'horario especial'),
    rotulo_acao = COALESCE(rotulo_acao, 'Ver detalhes')
WHERE
    (descricao IS NOT NULL AND (descricao LIKE '%Evento especial%' OR descricao LIKE '%evento especial%'))
    OR rotulo_acao IS NULL;
