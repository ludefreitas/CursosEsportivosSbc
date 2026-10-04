ALTER TABLE inscricoes_turma
    ADD COLUMN IF NOT EXISTS excecao_condicao VARCHAR(10) NULL AFTER publico_alvo;
