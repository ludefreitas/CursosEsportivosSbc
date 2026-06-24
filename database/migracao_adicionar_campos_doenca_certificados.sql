ALTER TABLE certificados_pessoa
ADD COLUMN doenca_declarada VARCHAR(180) NULL AFTER codigo_cid_validado,
ADD COLUMN doenca_validada VARCHAR(180) NULL AFTER doenca_declarada;

SHOW COLUMNS FROM certificados_pessoa LIKE 'doenca_declarada';
SHOW COLUMNS FROM certificados_pessoa LIKE 'doenca_validada';
