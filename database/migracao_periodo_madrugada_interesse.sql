-- Executar explicitamente antes de publicar a nova classificação dos períodos.
-- Mantém os contadores existentes. A leitura reclassifica os cliques pelo horário da turma.
ALTER TABLE curso_interesse_diario
    MODIFY COLUMN periodo ENUM('manha','tarde','noite','madrugada','sem_horario')
    NOT NULL DEFAULT 'sem_horario';
