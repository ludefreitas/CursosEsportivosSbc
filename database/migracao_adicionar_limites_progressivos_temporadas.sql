-- Libera progressivamente novas inscrições por CPF durante a temporada.

ALTER TABLE temporadas
    ADD COLUMN data_liberacao_segunda_inscricao DATETIME NULL AFTER limite_inscricoes_periodo,
    ADD COLUMN data_liberacao_inscricoes_adicionais DATETIME NULL AFTER data_liberacao_segunda_inscricao,
    ADD COLUMN limite_inscricoes_adicionais INT UNSIGNED NOT NULL DEFAULT 3 AFTER data_liberacao_inscricoes_adicionais;
