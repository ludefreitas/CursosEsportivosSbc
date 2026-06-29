ALTER TABLE horarios_semanais
ADD COLUMN criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata' AFTER idade_maxima;

SHOW COLUMNS FROM horarios_semanais LIKE 'criterio_faixa_etaria';
