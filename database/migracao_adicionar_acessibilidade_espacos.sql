ALTER TABLE espacos_treino
ADD COLUMN acessibilidade_deficiencias_indisponiveis TEXT NULL AFTER capacidade_base;

SHOW COLUMNS FROM espacos_treino LIKE 'acessibilidade_deficiencias_indisponiveis';
