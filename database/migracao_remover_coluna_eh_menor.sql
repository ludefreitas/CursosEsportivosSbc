USE cursos_esportivos_sbc;

-- Migracao para remover a coluna `eh_menor` da tabela `pessoas`.
-- A menoridade passa a ser calculada pela data de nascimento.

-- 1. Validacao recomendada antes da remocao:
-- confira registros sem data de nascimento preenchida.
SELECT id, nome_completo, cpf
FROM pessoas
WHERE data_nascimento IS NULL;

-- 2. Remocao da coluna legado.
ALTER TABLE pessoas
    DROP COLUMN eh_menor;

-- 3. Verificacao final da estrutura.
SHOW COLUMNS FROM pessoas;
