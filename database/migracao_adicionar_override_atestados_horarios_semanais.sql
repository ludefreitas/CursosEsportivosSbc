USE cursos_esportivos_sbc;

-- Migracao para permitir override por horario das exigencias de atestado.
ALTER TABLE horarios_semanais
ADD COLUMN regra_atestado_clinico ENUM('global', 'exigir', 'dispensar') NOT NULL DEFAULT 'global' AFTER idade_maxima,
ADD COLUMN regra_atestado_dermatologico ENUM('global', 'exigir', 'dispensar') NOT NULL DEFAULT 'global' AFTER regra_atestado_clinico;

SHOW COLUMNS FROM horarios_semanais LIKE 'regra_atestado_clinico';
SHOW COLUMNS FROM horarios_semanais LIKE 'regra_atestado_dermatologico';
