USE cursos_esportivos_sbc;

-- Migracao para adicionar o campo `sexo` na tabela `pessoas`.
-- Preencha os registros antigos manualmente apos executar este script.

ALTER TABLE pessoas
ADD COLUMN sexo ENUM('masculino', 'feminino', 'Sexo não declarado') NULL AFTER cpf;

SHOW COLUMNS FROM pessoas LIKE 'sexo';
