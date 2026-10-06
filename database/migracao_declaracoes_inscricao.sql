-- Executar separadamente antes de publicar as rotas de declaração e frequência.
CREATE TABLE IF NOT EXISTS declaracoes_inscricao (
    inscricao_turma_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    codigo_consulta CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_declaracao_inscricao FOREIGN KEY (inscricao_turma_id)
        REFERENCES inscricoes_turma(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
