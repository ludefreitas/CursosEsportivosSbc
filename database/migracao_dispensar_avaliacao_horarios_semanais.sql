-- Permite definir, por horário de treino ou aula, se a avaliação física prévia será dispensada.
-- Horários existentes continuam exigindo avaliação por padrão.
ALTER TABLE horarios_semanais
    ADD COLUMN dispensar_avaliacao_previa TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_horario;

-- Em horários destinados à própria avaliação, a exigência não se aplica.
UPDATE horarios_semanais
SET dispensar_avaliacao_previa = 1
WHERE tipo_horario = 'avaliacao';
