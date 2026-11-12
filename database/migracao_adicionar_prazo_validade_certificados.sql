ALTER TABLE certificados_pessoa
ADD COLUMN prazo_validade_meses TINYINT UNSIGNED NULL AFTER data_emissao;
