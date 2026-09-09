-- Corrige validações antigas gravadas com diferença de fuso horário entre PHP e MySQL.
-- A cópia de segurança permite conferir e restaurar os valores alterados, se necessário.
-- Execute este arquivo conectado ao banco correto da aplicação.

CREATE TABLE IF NOT EXISTS backup_horarios_validacao_20260909 (
    tabela_origem VARCHAR(40) NOT NULL,
    registro_id BIGINT UNSIGNED NOT NULL,
    validado_em_anterior DATETIME NULL,
    updated_at_anterior DATETIME NULL,
    salvo_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tabela_origem, registro_id)
) ENGINE=InnoDB;

-- Considera somente diferenças típicas de fuso: horas inteiras, de 1 a 14 horas.
-- Minutos e segundos precisam coincidir para evitar alterar atualizações comuns posteriores.
INSERT IGNORE INTO backup_horarios_validacao_20260909 (
    tabela_origem, registro_id, validado_em_anterior, updated_at_anterior
)
SELECT
    'certificados_pessoa', id, validado_em, updated_at
FROM certificados_pessoa
WHERE validado_em IS NOT NULL
  AND updated_at IS NOT NULL
  AND MINUTE(validado_em) = MINUTE(updated_at)
  AND SECOND(validado_em) = SECOND(updated_at)
  AND ABS(TIMESTAMPDIFF(HOUR, updated_at, validado_em)) BETWEEN 1 AND 14
  AND ABS(TIMESTAMPDIFF(SECOND, updated_at, validado_em)) MOD 3600 = 0;

INSERT IGNORE INTO backup_horarios_validacao_20260909 (
    tabela_origem, registro_id, validado_em_anterior, updated_at_anterior
)
SELECT
    'atestados_saude', id, validado_em, updated_at
FROM atestados_saude
WHERE validado_em IS NOT NULL
  AND updated_at IS NOT NULL
  AND MINUTE(validado_em) = MINUTE(updated_at)
  AND SECOND(validado_em) = SECOND(updated_at)
  AND ABS(TIMESTAMPDIFF(HOUR, updated_at, validado_em)) BETWEEN 1 AND 14
  AND ABS(TIMESTAMPDIFF(SECOND, updated_at, validado_em)) MOD 3600 = 0;

UPDATE certificados_pessoa AS certificado
INNER JOIN backup_horarios_validacao_20260909 AS backup
        ON backup.tabela_origem = 'certificados_pessoa'
       AND backup.registro_id = certificado.id
SET certificado.validado_em = certificado.updated_at
WHERE certificado.id = backup.registro_id
  AND certificado.validado_em <=> backup.validado_em_anterior
  AND certificado.updated_at <=> backup.updated_at_anterior;

UPDATE atestados_saude AS atestado
INNER JOIN backup_horarios_validacao_20260909 AS backup
        ON backup.tabela_origem = 'atestados_saude'
       AND backup.registro_id = atestado.id
SET atestado.validado_em = atestado.updated_at
WHERE atestado.id = backup.registro_id
  AND atestado.validado_em <=> backup.validado_em_anterior
  AND atestado.updated_at <=> backup.updated_at_anterior;

-- Conferência dos registros ajustados.
SELECT *
FROM backup_horarios_validacao_20260909
ORDER BY tabela_origem, registro_id;
