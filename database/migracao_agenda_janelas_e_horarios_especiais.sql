ALTER TABLE horarios_semanais
    ADD COLUMN janela_agendamento_tipo ENUM('semana_atual_proxima', 'janela_semanal_fixa', 'antecedencia') NOT NULL DEFAULT 'semana_atual_proxima' AFTER vagas_pvs,
    ADD COLUMN janela_abertura_dia_semana TINYINT UNSIGNED NULL AFTER janela_agendamento_tipo,
    ADD COLUMN janela_abertura_hora TIME NULL AFTER janela_abertura_dia_semana,
    ADD COLUMN janela_fechamento_dia_semana TINYINT UNSIGNED NULL AFTER janela_abertura_hora,
    ADD COLUMN janela_fechamento_hora TIME NULL AFTER janela_fechamento_dia_semana,
    ADD COLUMN janela_dias_antecedencia INT NOT NULL DEFAULT 7 AFTER janela_fechamento_hora,
    ADD COLUMN janela_horas_antes_fechamento INT NOT NULL DEFAULT 2 AFTER janela_dias_antecedencia;

CREATE TABLE IF NOT EXISTS agenda_horarios_especiais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    local_treino_id BIGINT UNSIGNED NULL,
    espaco_treino_id BIGINT UNSIGNED NULL,
    modalidade_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    idade_minima INT NOT NULL DEFAULT 0,
    idade_maxima INT NOT NULL DEFAULT 120,
    criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata',
    vagas_geral INT NOT NULL DEFAULT 0,
    vagas_pcd INT NOT NULL DEFAULT 0,
    vagas_plm INT NOT NULL DEFAULT 0,
    vagas_pvs INT NOT NULL DEFAULT 0,
    data_publicacao_inicio DATETIME NOT NULL,
    data_publicacao_fim DATETIME NOT NULL,
    publicar_pagina_inicial TINYINT(1) NOT NULL DEFAULT 0,
    publicar_blog TINYINT(1) NOT NULL DEFAULT 0,
    imagem_url VARCHAR(255) NULL,
    url_destino VARCHAR(255) NULL,
    rotulo_acao VARCHAR(80) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_agenda_horarios_especiais_periodo (data_inicio, data_fim, ativo),
    INDEX idx_agenda_horarios_especiais_publicacao (data_publicacao_inicio, data_publicacao_fim, ativo),
    CONSTRAINT fk_agenda_horario_especial_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id),
    CONSTRAINT fk_agenda_horario_especial_espaco FOREIGN KEY (espaco_treino_id) REFERENCES espacos_treino(id),
    CONSTRAINT fk_agenda_horario_especial_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agenda_horarios_especiais_inscricoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agenda_horario_especial_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NULL,
    conta_id BIGINT UNSIGNED NULL,
    nome_completo VARCHAR(180) NOT NULL,
    cpf CHAR(11) NOT NULL,
    data_nascimento DATE NOT NULL,
    publico_alvo ENUM('geral', 'pcd', 'plm', 'pvs') NOT NULL DEFAULT 'geral',
    aceite_termos TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('inscrito', 'cancelado') NOT NULL DEFAULT 'inscrito',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_agenda_horarios_especiais_inscricao_horario (agenda_horario_especial_id, status),
    INDEX idx_agenda_horarios_especiais_inscricao_cpf (cpf, status),
    CONSTRAINT fk_agenda_horario_especial_inscricao_horario FOREIGN KEY (agenda_horario_especial_id) REFERENCES agenda_horarios_especiais(id),
    CONSTRAINT fk_agenda_horario_especial_inscricao_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_agenda_horario_especial_inscricao_conta FOREIGN KEY (conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

SHOW COLUMNS FROM horarios_semanais LIKE 'janela_agendamento_tipo';
SHOW TABLES LIKE 'agenda_horarios_especiais';
