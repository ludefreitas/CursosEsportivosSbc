-- Estrutura das inscrições em cursos, com janelas configuráveis por temporada.

ALTER TABLE temporadas
    ADD COLUMN status ENUM('planejada', 'ativa', 'suspensa', 'encerrada', 'cancelada') NOT NULL DEFAULT 'planejada' AFTER data_fim,
    ADD COLUMN inscricoes_inicio DATETIME NULL AFTER data_fim,
    ADD COLUMN inscricoes_fim DATETIME NULL AFTER inscricoes_inicio,
    ADD COLUMN matriculas_inicio DATETIME NULL AFTER inscricoes_fim,
    ADD COLUMN matriculas_fim DATETIME NULL AFTER matriculas_inicio,
    ADD COLUMN inscricoes_abertas_inicio DATETIME NULL AFTER matriculas_fim,
    ADD COLUMN inscricoes_abertas_fim DATETIME NULL AFTER inscricoes_abertas_inicio,
    ADD COLUMN aulas_inicio DATE NULL AFTER inscricoes_abertas_fim,
    ADD COLUMN aulas_fim DATE NULL AFTER aulas_inicio,
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
    MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'aguardando_matricula';

UPDATE inscricoes_turma SET status = 'aguardando_matricula' WHERE status = 'inscrito';
UPDATE inscricoes_turma SET status = 'cancelada' WHERE status = 'cancelado';
UPDATE inscricoes_turma SET status = 'cancelada' WHERE status = 'concluido';

ALTER TABLE inscricoes_turma
    MODIFY COLUMN status ENUM('aguardando_matricula', 'matriculada', 'lista_espera', 'cancelada', 'excluida', 'excluida_por_falta', 'desistente', 'suspensa') NOT NULL DEFAULT 'aguardando_matricula',
    ADD COLUMN inscrito_por_conta_id BIGINT UNSIGNED NULL AFTER pessoa_id,
    ADD COLUMN cancelado_por_conta_id BIGINT UNSIGNED NULL AFTER inscrito_por_conta_id,
    ADD COLUMN motivo_status VARCHAR(255) NULL AFTER status,
    ADD COLUMN posicao_lista_espera INT UNSIGNED NULL AFTER motivo_status,
    ADD COLUMN suspensa_inicio DATETIME NULL AFTER motivo_status,
    ADD COLUMN suspensa_fim DATETIME NULL AFTER suspensa_inicio,
    ADD COLUMN updated_at DATETIME NULL AFTER motivo_status,
    ADD INDEX idx_inscricoes_turma_pessoa_status (turma_id, pessoa_id, status),
    ADD INDEX idx_inscricoes_pessoa_status (pessoa_id, status),
    ADD CONSTRAINT fk_inscricao_criador FOREIGN KEY (inscrito_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_inscricao_cancelador FOREIGN KEY (cancelado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL;

ALTER TABLE turmas
    ADD COLUMN criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata' AFTER idade_maxima,
    ADD COLUMN sexo ENUM('masculino', 'feminino') NULL AFTER criterio_faixa_etaria,
    ADD COLUMN vagas_espera_geral INT NOT NULL DEFAULT 0 AFTER vagas_pvs,
    ADD COLUMN vagas_espera_pcd INT NOT NULL DEFAULT 0 AFTER vagas_espera_geral,
    ADD COLUMN vagas_espera_plm INT NOT NULL DEFAULT 0 AFTER vagas_espera_pcd,
    ADD COLUMN vagas_espera_pvs INT NOT NULL DEFAULT 0 AFTER vagas_espera_plm;

CREATE TABLE IF NOT EXISTS inscricoes_turma_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscricao_turma_id BIGINT UNSIGNED NOT NULL,
    status_anterior VARCHAR(40) NULL,
    status_novo VARCHAR(40) NOT NULL,
    motivo VARCHAR(255) NULL,
    alterado_por_conta_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inscricao_historico (inscricao_turma_id, criado_em),
    CONSTRAINT fk_inscricao_historico_inscricao FOREIGN KEY (inscricao_turma_id) REFERENCES inscricoes_turma(id) ON DELETE CASCADE,
    CONSTRAINT fk_inscricao_historico_conta FOREIGN KEY (alterado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tokens_inscricao_turma (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token CHAR(64) NOT NULL UNIQUE,
    turma_id BIGINT UNSIGNED NOT NULL,
    cpf CHAR(11) NOT NULL,
    publico_alvo ENUM('geral', 'pcd', 'plm', 'pvs') NOT NULL DEFAULT 'geral',
    criado_por_conta_id BIGINT UNSIGNED NOT NULL,
    validade DATETIME NULL,
    usos_maximos INT UNSIGNED NOT NULL DEFAULT 1,
    usos_realizados INT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    motivo VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_inscricao_cpf_turma (cpf, turma_id, ativo),
    CONSTRAINT fk_token_inscricao_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_token_inscricao_conta FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

SELECT 'Migração do sistema de inscrições concluída.' AS resultado;
