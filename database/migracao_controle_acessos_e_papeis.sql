USE cursos_esportivos_sbc;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contas'
      AND COLUMN_NAME = 'ultimo_acesso_em'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE contas
        ADD COLUMN ultimo_acesso_em DATETIME NULL AFTER ativo,
        ADD COLUMN ultimo_acesso_ip VARCHAR(45) NULL AFTER ultimo_acesso_em,
        ADD COLUMN ultimo_acesso_user_agent VARCHAR(255) NULL AFTER ultimo_acesso_ip,
        ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contas'
      AND INDEX_NAME = 'idx_contas_ultimo_acesso'
);

SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE contas ADD INDEX idx_contas_ultimo_acesso (ultimo_acesso_em)',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'conta_papeis'
      AND COLUMN_NAME = 'atribuido_em'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE conta_papeis
        ADD COLUMN atribuido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER papel_id,
        ADD COLUMN atribuido_por_conta_id BIGINT UNSIGNED NULL AFTER atribuido_em,
        ADD COLUMN origem_atribuicao VARCHAR(50) NULL AFTER atribuido_por_conta_id',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_conta_papeis_atribuido_por'
);

SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE conta_papeis
        ADD CONSTRAINT fk_conta_papeis_atribuido_por
            FOREIGN KEY (atribuido_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS conta_papeis_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NOT NULL,
    papel_id BIGINT UNSIGNED NOT NULL,
    acao ENUM('atribuicao_manual', 'remocao_manual', 'remocao_automatica_inatividade') NOT NULL,
    realizado_por_conta_id BIGINT UNSIGNED NULL,
    ip_usuario VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    motivo VARCHAR(255) NULL,
    ultimo_acesso_referencia_em DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conta_papeis_historico_conta (conta_id, created_at),
    INDEX idx_conta_papeis_historico_acao (acao, created_at),
    CONSTRAINT fk_conta_papeis_hist_conta FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE CASCADE,
    CONSTRAINT fk_conta_papeis_hist_papel FOREIGN KEY (papel_id) REFERENCES papeis(id) ON DELETE CASCADE,
    CONSTRAINT fk_conta_papeis_hist_realizado_por FOREIGN KEY (realizado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contas_acessos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NOT NULL,
    ip_usuario VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    caminho VARCHAR(255) NULL,
    session_id VARCHAR(128) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contas_acessos_conta (conta_id, created_at),
    INDEX idx_contas_acessos_ip (ip_usuario, created_at),
    CONSTRAINT fk_contas_acessos_conta FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

UPDATE contas c
SET c.ultimo_acesso_em = COALESCE(
    c.ultimo_acesso_em,
    (
        SELECT MAX(la.created_at)
        FROM logs_auditoria la
        WHERE la.conta_id = c.id
          AND la.tipo_evento = 'autenticacao.login'
    ),
    c.created_at
)
WHERE c.ultimo_acesso_em IS NULL;

UPDATE conta_papeis cp
INNER JOIN contas c ON c.id = cp.conta_id
SET cp.atribuido_em = COALESCE(c.created_at, cp.atribuido_em),
    cp.origem_atribuicao = COALESCE(cp.origem_atribuicao, 'migracao')
WHERE cp.origem_atribuicao IS NULL;

SHOW COLUMNS FROM contas LIKE 'ultimo_acesso_em';
SHOW COLUMNS FROM conta_papeis LIKE 'atribuido_em';
SHOW TABLES LIKE 'conta_papeis_historico';
SHOW TABLES LIKE 'contas_acessos';
