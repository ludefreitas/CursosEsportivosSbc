ALTER TABLE certificados_pessoa
ADD COLUMN observacao_validacao TEXT NULL AFTER observacoes;

SHOW COLUMNS FROM certificados_pessoa LIKE 'observacao_validacao';
