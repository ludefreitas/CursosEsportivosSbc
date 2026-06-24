USE cursos_esportivos_sbc;

-- Migracao para adicionar a restricao opcional de sexo nos horarios semanais.
ALTER TABLE horarios_semanais
ADD COLUMN sexo ENUM('masculino', 'feminino') NULL AFTER idade_maxima;

SHOW COLUMNS FROM horarios_semanais LIKE 'sexo';
