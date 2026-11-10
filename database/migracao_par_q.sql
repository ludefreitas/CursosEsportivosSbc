CREATE TABLE IF NOT EXISTS questionarios_par_q (
    pessoa_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    respondido_por_conta_id BIGINT UNSIGNED NOT NULL,
    respostas JSON NOT NULL,
    tem_resposta_positiva TINYINT(1) NOT NULL,
    termo_aceito TINYINT(1) NOT NULL,
    enviado_em DATETIME NOT NULL,
    vence_em DATE NOT NULL,
    renovar_em DATE NOT NULL,
    CONSTRAINT fk_par_q_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_par_q_conta FOREIGN KEY (respondido_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;