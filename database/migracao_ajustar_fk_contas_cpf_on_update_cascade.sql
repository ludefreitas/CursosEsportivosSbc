USE cursos_esportivos_sbc;

-- Ajusta a chave estrangeira de `contas(cpf)` para permitir atualizacao
-- consistente do CPF em `pessoas` sem quebrar o vinculo da conta.

ALTER TABLE contas
    DROP FOREIGN KEY fk_contas_pessoa_cpf;

ALTER TABLE contas
    ADD CONSTRAINT fk_contas_pessoa_cpf
        FOREIGN KEY (cpf) REFERENCES pessoas(cpf) ON UPDATE CASCADE;

SHOW CREATE TABLE contas;
