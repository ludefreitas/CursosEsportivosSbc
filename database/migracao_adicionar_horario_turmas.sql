-- Dias da semana e horário regular das aulas de cada turma.

ALTER TABLE turmas
    ADD COLUMN dias_semana VARCHAR(120) NULL AFTER nome,
    ADD COLUMN hora_inicio TIME NULL AFTER dias_semana,
    ADD COLUMN hora_fim TIME NULL AFTER hora_inicio;
