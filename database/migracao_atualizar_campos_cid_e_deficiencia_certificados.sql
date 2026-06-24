ALTER TABLE certificados_pessoa
CHANGE COLUMN codigo_cid codigo_cid_declarado VARCHAR(20) NULL,
ADD COLUMN codigo_cid_validado VARCHAR(20) NULL AFTER codigo_cid_declarado,
ADD COLUMN tipos_deficiencia_pcd TEXT NULL AFTER codigo_cid_validado;

SHOW COLUMNS FROM certificados_pessoa LIKE 'codigo_cid_declarado';
SHOW COLUMNS FROM certificados_pessoa LIKE 'codigo_cid_validado';
SHOW COLUMNS FROM certificados_pessoa LIKE 'tipos_deficiencia_pcd';
