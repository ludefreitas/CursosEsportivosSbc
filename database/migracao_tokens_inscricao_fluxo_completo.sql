-- Executar uma única vez, separadamente da aplicação web.
ALTER TABLE tokens_inscricao_turma
    ADD COLUMN numero_token CHAR(4) NULL AFTER token,
    ADD COLUMN status ENUM('ativo','usado','cancelado','expirado','excluido') NOT NULL DEFAULT 'ativo' AFTER motivo,
    ADD COLUMN inscricao_turma_id BIGINT UNSIGNED NULL AFTER status,
    ADD COLUMN usado_em DATETIME NULL AFTER inscricao_turma_id,
    ADD COLUMN cancelado_em DATETIME NULL AFTER usado_em,
    ADD COLUMN excluido_em DATETIME NULL AFTER cancelado_em,
    ADD INDEX idx_token_numero_turma_status (numero_token, turma_id, status),
    ADD INDEX idx_token_cpf_status (cpf, status),
    ADD CONSTRAINT fk_token_inscricao_utilizada FOREIGN KEY (inscricao_turma_id) REFERENCES inscricoes_turma(id) ON DELETE SET NULL;

UPDATE tokens_inscricao_turma
SET status = CASE
    WHEN usos_realizados > 0 THEN 'usado'
    WHEN validade IS NOT NULL AND validade < NOW() THEN 'expirado'
    WHEN ativo = 0 THEN 'cancelado'
    ELSE 'ativo'
END;
