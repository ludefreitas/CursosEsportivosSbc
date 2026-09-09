-- Registra quando a vaga da lista de espera foi comunicada ao usuário.
-- Execute uma única vez no banco de dados de cada ambiente.

ALTER TABLE inscricoes_turma
    ADD COLUMN vaga_informada_em DATETIME NULL AFTER motivo_status;

ALTER TABLE inscricoes_turma_historico
    ADD COLUMN vaga_informada_em DATETIME NULL AFTER alterado_por_conta_id;
