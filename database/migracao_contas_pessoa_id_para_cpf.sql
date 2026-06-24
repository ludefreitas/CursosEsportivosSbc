USE cursos_esportivos_sbc;

-- Migracao da tabela `contas` para deixar de usar `pessoa_id`
-- e passar a se relacionar com `pessoas` pela coluna `cpf`.
-- A ordem das etapas foi pensada para preservar os dados existentes.

-- 1. Garantia previa: a coluna `cpf` passa a existir em `contas`.
ALTER TABLE contas
    ADD COLUMN cpf CHAR(11) NULL AFTER id;

-- 2. Copia o CPF correto de cada conta a partir do vinculo antigo com `pessoa_id`.
UPDATE contas c
INNER JOIN pessoas p ON p.id = c.pessoa_id
SET c.cpf = p.cpf
WHERE c.cpf IS NULL OR c.cpf = '';

-- 3. Validacao manual recomendada:
-- Se a consulta abaixo retornar qualquer linha, interrompa a migracao
-- e corrija os registros antes de continuar.
SELECT id, pessoa_id, cpf
FROM contas
WHERE cpf IS NULL OR CHAR_LENGTH(cpf) <> 11;

-- 4. Torna o novo vinculo obrigatorio e unico.
ALTER TABLE contas
    MODIFY COLUMN cpf CHAR(11) NOT NULL,
    ADD UNIQUE KEY uniq_contas_cpf (cpf);

-- 5. Remove a chave estrangeira antiga baseada em `pessoa_id`.
ALTER TABLE contas
    DROP FOREIGN KEY fk_contas_pessoa;

-- 6. Cria a nova chave estrangeira baseada no CPF funcional da pessoa.
ALTER TABLE contas
    ADD CONSTRAINT fk_contas_pessoa_cpf
        FOREIGN KEY (cpf) REFERENCES pessoas(cpf) ON UPDATE CASCADE;

-- 7. Remove a coluna antiga para concluir a desvinculacao do identificador interno.
ALTER TABLE contas
    DROP COLUMN pessoa_id;

-- 8. Verificacao final recomendada apos a migracao.
SELECT c.id, c.cpf, p.nome_completo
FROM contas c
INNER JOIN pessoas p ON p.cpf = c.cpf
ORDER BY c.id;
