-- Estrutura das inscrições em cursos, com janelas configuráveis por temporada.

ALTER TABLE temporadas
    ADD COLUMN inscricoes_inicio DATETIME NULL AFTER data_fim,
    ADD COLUMN inscricoes_fim DATETIME NULL AFTER inscricoes_inicio,
    ADD COLUMN permitir_inscricao_por_cpf TINYINT(1) NOT NULL DEFAULT 0 AFTER inscricoes_fim,
    ADD COLUMN permitir_inscricao_logada TINYINT(1) NOT NULL DEFAULT 1 AFTER permitir_inscricao_por_cpf,
    ADD COLUMN limite_inscricoes_periodo INT UNSIGNED NOT NULL DEFAULT 1 AFTER permitir_inscricao_logada;

CREATE TABLE IF NOT EXISTS temporadas_janelas_inscricao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    temporada_id BIGINT UNSIGNED NOT NULL,
    modalidade_id BIGINT UNSIGNED NULL,
    numero_inscricao INT UNSIGNED NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    limite_inscricoes_pessoa INT UNSIGNED NOT NULL DEFAULT 1,
    forcar_lista_espera TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uk_temporada_janela_inscricao (temporada_id, modalidade_id, numero_inscricao),
    INDEX idx_temporadas_janelas_periodo (temporada_id, data_inicio, data_fim, ativo),
    CONSTRAINT fk_temporada_janela_temporada FOREIGN KEY (temporada_id) REFERENCES temporadas(id) ON DELETE CASCADE,
    CONSTRAINT fk_temporada_janela_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE inscricoes_turma
    MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'aguardando_matricula';

UPDATE inscricoes_turma SET status = 'aguardando_matricula' WHERE status = 'inscrito';
UPDATE inscricoes_turma SET status = 'cancelada' WHERE status = 'cancelado';
UPDATE inscricoes_turma SET status = 'encerrada' WHERE status = 'concluido';

ALTER TABLE inscricoes_turma
    MODIFY COLUMN status ENUM('aguardando_matricula', 'matriculada', 'lista_espera', 'cancelada', 'excluida', 'excluida_por_falta', 'desistente', 'encerrada') NOT NULL DEFAULT 'aguardando_matricula',
    ADD COLUMN inscrito_por_conta_id BIGINT UNSIGNED NULL AFTER pessoa_id,
    ADD COLUMN cancelado_por_conta_id BIGINT UNSIGNED NULL AFTER inscrito_por_conta_id,
    ADD COLUMN motivo_status VARCHAR(255) NULL AFTER status,
    ADD COLUMN updated_at DATETIME NULL AFTER motivo_status,
    ADD INDEX idx_inscricoes_turma_pessoa_status (turma_id, pessoa_id, status),
    ADD INDEX idx_inscricoes_pessoa_status (pessoa_id, status),
    ADD CONSTRAINT fk_inscricao_criador FOREIGN KEY (inscrito_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_inscricao_cancelador FOREIGN KEY (cancelado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL;

SELECT 'Migração do sistema de inscrições concluída.' AS resultado;