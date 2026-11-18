-- Executar separadamente, antes de publicar o código desta funcionalidade.
ALTER TABLE inscricoes_turma
    ADD COLUMN suspensao_libera_vaga TINYINT(1) NOT NULL DEFAULT 0 AFTER suspensa_fim,
    ADD INDEX idx_inscricao_suspensao_prazo (status, suspensa_fim, turma_id);
