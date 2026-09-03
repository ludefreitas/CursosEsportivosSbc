ALTER TABLE temporadas
    ADD COLUMN permitir_multiplas_inscricoes_modalidade TINYINT(1) NOT NULL DEFAULT 0 AFTER limite_inscricoes_adicionais,
    ADD COLUMN limite_inscricoes_modalidade INT UNSIGNED NOT NULL DEFAULT 1 AFTER permitir_multiplas_inscricoes_modalidade,
    ADD COLUMN data_liberacao_multiplas_inscricoes_modalidade DATETIME NULL AFTER limite_inscricoes_modalidade;

ALTER TABLE cronogramas_modalidade
    ADD COLUMN permitir_multiplas_inscricoes_modalidade TINYINT(1) NOT NULL DEFAULT 0 AFTER link_edital,
    ADD COLUMN limite_inscricoes_modalidade INT UNSIGNED NOT NULL DEFAULT 1 AFTER permitir_multiplas_inscricoes_modalidade,
    ADD COLUMN data_liberacao_multiplas_inscricoes_modalidade DATETIME NULL AFTER limite_inscricoes_modalidade;

ALTER TABLE turmas
    ADD COLUMN inscricoes_abertas TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo;

UPDATE turmas SET inscricoes_abertas = 1 WHERE ativo = 1;

UPDATE cronogramas_modalidade cm
INNER JOIN temporadas te ON te.id = cm.temporada_id
SET cm.permitir_multiplas_inscricoes_modalidade = te.permitir_multiplas_inscricoes_modalidade,
    cm.limite_inscricoes_modalidade = te.limite_inscricoes_modalidade,
    cm.data_liberacao_multiplas_inscricoes_modalidade = te.data_liberacao_multiplas_inscricoes_modalidade;
