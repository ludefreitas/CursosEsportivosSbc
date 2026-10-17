-- Executar uma única vez, separadamente, antes de publicar os novos formulários.
-- Não altera observações existentes nem preenche registros antigos.
ALTER TABLE temporadas ADD COLUMN observacao TEXT NULL;
ALTER TABLE cronogramas_modalidade ADD COLUMN observacao TEXT NULL;
