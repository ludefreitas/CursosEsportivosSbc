ALTER TABLE turmas
    ADD COLUMN programa ENUM(
        'Corpo em Ação',
        'Hora do Treino',
        'Campeões da Vida',
        'GR São Bernardo'
    ) NULL AFTER nome;
