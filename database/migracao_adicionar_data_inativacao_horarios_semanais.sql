ALTER TABLE horarios_semanais
    ADD COLUMN data_inativacao DATE NULL AFTER ativo;

SHOW COLUMNS FROM horarios_semanais LIKE 'data_inativacao';
