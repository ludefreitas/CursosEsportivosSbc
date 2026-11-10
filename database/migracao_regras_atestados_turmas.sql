-- As turmas existentes são preenchidas com a opção Dispensar nesta turma.
ALTER TABLE turmas
    ADD COLUMN regra_atestado_clinico ENUM('global', 'exigir', 'dispensar') NOT NULL DEFAULT 'dispensar',
    ADD COLUMN regra_atestado_dermatologico ENUM('global', 'exigir', 'dispensar') NOT NULL DEFAULT 'dispensar';
