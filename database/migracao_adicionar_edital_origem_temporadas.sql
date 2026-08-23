-- Acrescenta à temporada a instituição gestora e os dados do edital de inscrições.

ALTER TABLE temporadas
    ADD COLUMN origem_temporada VARCHAR(180) NULL AFTER nome,
    ADD COLUMN possui_edital TINYINT(1) NOT NULL DEFAULT 0 AFTER origem_temporada,
    ADD COLUMN numero_edital VARCHAR(100) NULL AFTER possui_edital,
    ADD COLUMN link_edital VARCHAR(2048) NULL AFTER numero_edital;
