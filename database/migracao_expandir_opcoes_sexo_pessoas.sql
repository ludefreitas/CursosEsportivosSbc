USE cursos_esportivos_sbc;

-- Migracao para bases que ja possuem a coluna `sexo` criada sem a opcao
-- `Sexo não declarado`.

ALTER TABLE pessoas
MODIFY COLUMN sexo ENUM('masculino', 'feminino', 'Sexo não declarado') NULL AFTER cpf;

SHOW COLUMNS FROM pessoas LIKE 'sexo';
