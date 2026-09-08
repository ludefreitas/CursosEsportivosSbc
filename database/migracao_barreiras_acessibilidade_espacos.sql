ALTER TABLE espacos_treino
    ADD COLUMN IF NOT EXISTS acessibilidade_barreiras TEXT NULL
    AFTER acessibilidade_deficiencias_indisponiveis;

SELECT id, nome, acessibilidade_deficiencias_indisponiveis, acessibilidade_barreiras
FROM espacos_treino
ORDER BY id;
