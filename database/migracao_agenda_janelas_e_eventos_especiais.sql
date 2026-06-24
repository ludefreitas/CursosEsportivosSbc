ALTER TABLE horarios_semanais
    ADD COLUMN janela_agendamento_tipo ENUM('semana_atual_proxima', 'janela_semanal_fixa', 'antecedencia') NOT NULL DEFAULT 'semana_atual_proxima' AFTER vagas_pvs,
    ADD COLUMN janela_abertura_dia_semana TINYINT UNSIGNED NULL AFTER janela_agendamento_tipo,
    ADD COLUMN janela_abertura_hora TIME NULL AFTER janela_abertura_dia_semana,
    ADD COLUMN janela_fechamento_dia_semana TINYINT UNSIGNED NULL AFTER janela_abertura_hora,
    ADD COLUMN janela_fechamento_hora TIME NULL AFTER janela_fechamento_dia_semana,
    ADD COLUMN janela_dias_antecedencia INT NOT NULL DEFAULT 7 AFTER janela_fechamento_hora,
    ADD COLUMN janela_horas_antes_fechamento INT NOT NULL DEFAULT 2 AFTER janela_dias_antecedencia;

CREATE TABLE IF NOT EXISTS agenda_eventos_especiais (
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
    INDEX idx_agenda_eventos_especiais_periodo (data_inicio, data_fim, ativo),
    INDEX idx_agenda_eventos_especiais_publicacao (data_publicacao_inicio, data_publicacao_fim, ativo),
    CONSTRAINT fk_agenda_evento_especial_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id),
    CONSTRAINT fk_agenda_evento_especial_espaco FOREIGN KEY (espaco_treino_id) REFERENCES espacos_treino(id),
    CONSTRAINT fk_agenda_evento_especial_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agenda_eventos_especiais_inscricoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agenda_evento_especial_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NULL,
    conta_id BIGINT UNSIGNED NULL,
    nome_completo VARCHAR(180) NOT NULL,
    cpf CHAR(11) NOT NULL,
    data_nascimento DATE NOT NULL,
    aceite_termos TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('inscrito', 'cancelado') NOT NULL DEFAULT 'inscrito',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_agenda_eventos_especiais_inscricao_evento (agenda_evento_especial_id, status),
    INDEX idx_agenda_eventos_especiais_inscricao_cpf (cpf, status),
    CONSTRAINT fk_agenda_evento_especial_inscricao_evento FOREIGN KEY (agenda_evento_especial_id) REFERENCES agenda_eventos_especiais(id),
    CONSTRAINT fk_agenda_evento_especial_inscricao_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_agenda_evento_especial_inscricao_conta FOREIGN KEY (conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

ALTER TABLE agenda_eventos_especiais
    ADD COLUMN IF NOT EXISTS idade_minima INT NOT NULL DEFAULT 0 AFTER data_fim,
    ADD COLUMN IF NOT EXISTS idade_maxima INT NOT NULL DEFAULT 120 AFTER idade_minima,
    ADD COLUMN IF NOT EXISTS data_publicacao_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER idade_maxima,
    ADD COLUMN IF NOT EXISTS data_publicacao_fim DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER data_publicacao_inicio,
    ADD COLUMN IF NOT EXISTS publicar_pagina_inicial TINYINT(1) NOT NULL DEFAULT 0 AFTER data_publicacao_fim,
    ADD COLUMN IF NOT EXISTS publicar_blog TINYINT(1) NOT NULL DEFAULT 0 AFTER publicar_pagina_inicial,
    ADD COLUMN IF NOT EXISTS imagem_url VARCHAR(255) NULL AFTER publicar_blog;

SHOW COLUMNS FROM horarios_semanais LIKE 'janela_agendamento_tipo';
SHOW TABLES LIKE 'agenda_eventos_especiais';
