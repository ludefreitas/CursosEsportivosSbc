-- Restrição por um ou mais níveis em turmas e horários semanais.
INSERT IGNORE INTO niveis_modalidade (slug, nome) VALUES
    ('iniciante', 'Iniciante'),
    ('intermediario', 'Intermediário'),
    ('avancado', 'Avançado'),
    ('treinamento', 'Treinamento');

ALTER TABLE turmas
    ADD COLUMN IF NOT EXISTS niveis_aceitos_json JSON NULL AFTER nivel_modalidade_id;

ALTER TABLE horarios_semanais
    ADD COLUMN IF NOT EXISTS niveis_aceitos_json JSON NULL AFTER dispensar_avaliacao_previa;

-- Cada novo certificado preserva o anterior como inativo, formando a evolução da pessoa.
ALTER TABLE certificados_nivel_modalidade
    ADD COLUMN IF NOT EXISTS tipo_movimentacao ENUM('concessao','evolucao','rebaixamento') NOT NULL DEFAULT 'concessao' AFTER observacoes,
    ADD COLUMN IF NOT EXISTS nivel_anterior_id BIGINT UNSIGNED NULL AFTER tipo_movimentacao,
    ADD COLUMN IF NOT EXISTS avaliacao_fisica_id BIGINT UNSIGNED NULL AFTER nivel_anterior_id;
