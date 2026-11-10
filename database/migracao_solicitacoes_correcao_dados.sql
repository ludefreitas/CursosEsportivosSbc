CREATE TABLE IF NOT EXISTS solicitacoes_correcao_dados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    solicitante_conta_id BIGINT UNSIGNED NOT NULL,
    campo ENUM('cpf', 'data_nascimento') NOT NULL,
    valor_solicitado VARCHAR(11) NOT NULL,
    documento_mime VARCHAR(40) NOT NULL,
    documento MEDIUMBLOB NOT NULL,
    status ENUM('recebido', 'em_analise', 'aguardando_documento', 'concluido') NOT NULL DEFAULT 'recebido',
    revisao INT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluido_em DATETIME NULL,
    INDEX idx_correcao_solicitante (solicitante_conta_id, criado_em),
    INDEX idx_correcao_pessoa (pessoa_id, campo, status),
    CONSTRAINT fk_correcao_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_correcao_solicitante FOREIGN KEY (solicitante_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
