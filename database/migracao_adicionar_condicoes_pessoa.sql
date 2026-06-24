USE cursos_esportivos_sbc;

-- Migracao para registrar condicoes especiais declaradas pela pessoa.
ALTER TABLE pessoas
ADD COLUMN eh_pcd TINYINT(1) NOT NULL DEFAULT 0 AFTER responsavel2_cpf,
ADD COLUMN eh_pvs TINYINT(1) NOT NULL DEFAULT 0 AFTER eh_pcd,
ADD COLUMN eh_plm TINYINT(1) NOT NULL DEFAULT 0 AFTER eh_pvs;

SHOW COLUMNS FROM pessoas LIKE 'eh_pcd';
SHOW COLUMNS FROM pessoas LIKE 'eh_pvs';
SHOW COLUMNS FROM pessoas LIKE 'eh_plm';
