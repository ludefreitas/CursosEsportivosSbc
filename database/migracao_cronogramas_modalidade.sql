CREATE TABLE IF NOT EXISTS cronogramas_modalidade (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    temporada_id BIGINT UNSIGNED NOT NULL,
    modalidade_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    inscricoes_inicio DATETIME NULL,
    inscricoes_fim DATETIME NULL,
    matriculas_inicio DATETIME NULL,
    matriculas_fim DATETIME NULL,
    permitir_inscricao_periodo_matricula TINYINT(1) NOT NULL DEFAULT 0,
    inscricoes_abertas_inicio DATETIME NULL,
    inscricoes_abertas_fim DATETIME NULL,
    aulas_inicio DATE NULL,
    aulas_fim DATE NULL,
    possui_edital TINYINT(1) NOT NULL DEFAULT 0,
    numero_edital VARCHAR(100) NULL,
    link_edital VARCHAR(2048) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cronograma_modalidade_temporada FOREIGN KEY (temporada_id) REFERENCES temporadas(id),
    CONSTRAINT fk_cronograma_modalidade_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cronogramas_modalidade ADD COLUMN IF NOT EXISTS data_inicio DATE NULL AFTER nome;
ALTER TABLE cronogramas_modalidade ADD COLUMN IF NOT EXISTS data_fim DATE NULL AFTER data_inicio;

UPDATE cronogramas_modalidade cm
INNER JOIN temporadas te ON te.id = cm.temporada_id
SET cm.data_inicio = COALESCE(cm.data_inicio, te.data_inicio),
    cm.data_fim = COALESCE(cm.data_fim, te.data_fim);

ALTER TABLE cronogramas_modalidade MODIFY COLUMN data_inicio DATE NOT NULL;
ALTER TABLE cronogramas_modalidade MODIFY COLUMN data_fim DATE NOT NULL;

ALTER TABLE turmas ADD COLUMN cronograma_modalidade_id BIGINT UNSIGNED NULL AFTER modalidade_id;

INSERT INTO cronogramas_modalidade (
    temporada_id, modalidade_id, nome, data_inicio, data_fim, inscricoes_inicio, inscricoes_fim,
    matriculas_inicio, matriculas_fim, permitir_inscricao_periodo_matricula, inscricoes_abertas_inicio,
    inscricoes_abertas_fim, aulas_inicio, aulas_fim, possui_edital,
    numero_edital, link_edital
)
SELECT DISTINCT
    t.temporada_id,
    t.modalidade_id,
    CONCAT(m.nome, ' - ', te.nome, ' (padrão)'),
    te.data_inicio,
    te.data_fim,
    te.inscricoes_inicio,
    te.inscricoes_fim,
    te.matriculas_inicio,
    te.matriculas_fim,
    te.permitir_inscricao_periodo_matricula,
    te.inscricoes_abertas_inicio,
    te.inscricoes_abertas_fim,
    te.aulas_inicio,
    te.aulas_fim,
    te.possui_edital,
    te.numero_edital,
    te.link_edital
FROM turmas t
INNER JOIN temporadas te ON te.id = t.temporada_id
INNER JOIN modalidades m ON m.id = t.modalidade_id
LEFT JOIN cronogramas_modalidade cm
    ON cm.temporada_id = t.temporada_id AND cm.modalidade_id = t.modalidade_id
WHERE cm.id IS NULL;

UPDATE turmas t
INNER JOIN cronogramas_modalidade cm
    ON cm.temporada_id = t.temporada_id AND cm.modalidade_id = t.modalidade_id
SET t.cronograma_modalidade_id = cm.id
WHERE t.cronograma_modalidade_id IS NULL;

ALTER TABLE turmas MODIFY COLUMN cronograma_modalidade_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE turmas ADD CONSTRAINT fk_turmas_cronograma_modalidade
    FOREIGN KEY (cronograma_modalidade_id) REFERENCES cronogramas_modalidade(id);
