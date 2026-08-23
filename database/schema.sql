CREATE DATABASE IF NOT EXISTS cursos_esportivos_sbc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cursos_esportivos_sbc;

CREATE TABLE IF NOT EXISTS pessoas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(180) NOT NULL,
    cpf CHAR(11) NOT NULL UNIQUE,
    sexo ENUM('masculino', 'feminino', 'Sexo não declarado') NULL,
    data_nascimento DATE NULL,
    email VARCHAR(180) NULL,
    telefone_whatsapp VARCHAR(30) NULL,
    numero_cartao_sus CHAR(15) NULL,
    cep VARCHAR(10) NULL,
    logradouro VARCHAR(180) NULL,
    numero_endereco VARCHAR(20) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    contato_emergencia_nome VARCHAR(180) NULL,
    contato_emergencia_telefone VARCHAR(30) NULL,
    responsavel1_nome VARCHAR(180) NULL,
    responsavel1_cpf CHAR(11) NULL,
    responsavel2_nome VARCHAR(180) NULL,
    responsavel2_cpf CHAR(11) NULL,
    eh_pcd TINYINT(1) NOT NULL DEFAULT 0,
    eh_pvs TINYINT(1) NOT NULL DEFAULT 0,
    eh_plm TINYINT(1) NOT NULL DEFAULT 0,
    cadastro_completo TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_pessoas_nome (nome_completo),
    INDEX idx_pessoas_cadastro (cadastro_completo),
    CONSTRAINT chk_pessoas_cartao_sus_15_digitos
        CHECK (numero_cartao_sus IS NULL OR numero_cartao_sus REGEXP '^[0-9]{15}$')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cadastros_externos_migracao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_externo BIGINT UNSIGNED NOT NULL,
    cpf CHAR(11) NOT NULL,
    nome_completo VARCHAR(180) NOT NULL,
    data_nascimento DATE NULL,
    sexo VARCHAR(40) NULL,
    telefone_whatsapp VARCHAR(30) NULL,
    email VARCHAR(180) NULL,
    numero_cartao_sus VARCHAR(30) NULL,
    cep VARCHAR(10) NULL,
    logradouro VARCHAR(180) NULL,
    numero_endereco VARCHAR(30) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    contato_emergencia_nome VARCHAR(180) NULL,
    contato_emergencia_telefone VARCHAR(30) NULL,
    responsavel1_nome VARCHAR(180) NULL,
    responsavel1_cpf CHAR(11) NULL,
    responsavel2_nome VARCHAR(180) NULL,
    responsavel2_cpf CHAR(11) NULL,
    situacao_origem VARCHAR(80) NULL,
    data_inclusao_origem DATETIME NULL,
    data_alteracao_origem DATETIME NULL,
    status_migracao VARCHAR(20) NOT NULL DEFAULT 'pendente',
    pessoa_id BIGINT UNSIGNED NULL,
    importado_por_conta_id BIGINT UNSIGNED NULL,
    importado_em DATETIME NOT NULL,
    migrado_em DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_cadastros_externos_id (id_externo),
    INDEX idx_cadastros_externos_cpf_status (cpf, status_migracao),
    INDEX idx_cadastros_externos_nome (nome_completo),
    INDEX idx_cadastros_externos_status (status_migracao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS migracoes_fontes_externas (
    chave VARCHAR(80) PRIMARY KEY,
    concluida_em DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locais_externos_migracao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_externo BIGINT UNSIGNED NOT NULL,
    apelido_local VARCHAR(100) NULL,
    nome_local VARCHAR(150) NOT NULL,
    logradouro VARCHAR(180) NULL,
    numero_endereco VARCHAR(20) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    telefone VARCHAR(30) NULL,
    cep CHAR(8) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    importado_em DATETIME NOT NULL,
    UNIQUE KEY uk_local_externo (id_externo),
    INDEX idx_local_externo_nome (nome_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS espacos_externos_migracao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_externo BIGINT UNSIGNED NOT NULL,
    local_id_externo BIGINT UNSIGNED NOT NULL,
    nome_espaco VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    observacao VARCHAR(255) NULL,
    area_espaco DECIMAL(12,2) NULL,
    nome_local VARCHAR(150) NOT NULL,
    apelido_local VARCHAR(100) NULL,
    importado_em DATETIME NOT NULL,
    UNIQUE KEY uk_espaco_externo (id_externo),
    INDEX idx_espaco_externo_nome (nome_espaco),
    INDEX idx_espaco_externo_local (nome_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cpf CHAR(11) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acesso_em DATETIME NULL,
    ultimo_acesso_ip VARCHAR(45) NULL,
    ultimo_acesso_user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_contas_ultimo_acesso (ultimo_acesso_em),
    CONSTRAINT fk_contas_pessoa_cpf FOREIGN KEY (cpf) REFERENCES pessoas(cpf) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS papeis (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conta_papeis (
    conta_id BIGINT UNSIGNED NOT NULL,
    papel_id BIGINT UNSIGNED NOT NULL,
    atribuido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atribuido_por_conta_id BIGINT UNSIGNED NULL,
    origem_atribuicao VARCHAR(50) NULL,
    PRIMARY KEY (conta_id, papel_id),
    CONSTRAINT fk_conta_papeis_conta FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE CASCADE,
    CONSTRAINT fk_conta_papeis_papel FOREIGN KEY (papel_id) REFERENCES papeis(id) ON DELETE CASCADE,
    CONSTRAINT fk_conta_papeis_atribuido_por FOREIGN KEY (atribuido_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conta_papeis_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NOT NULL,
    papel_id BIGINT UNSIGNED NOT NULL,
    acao ENUM('atribuicao_manual', 'remocao_manual', 'remocao_automatica_inatividade') NOT NULL,
    realizado_por_conta_id BIGINT UNSIGNED NULL,
    ip_usuario VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    motivo VARCHAR(255) NULL,
    ultimo_acesso_referencia_em DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conta_papeis_historico_conta (conta_id, created_at),
    INDEX idx_conta_papeis_historico_acao (acao, created_at),
    CONSTRAINT fk_conta_papeis_hist_conta FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE CASCADE,
    CONSTRAINT fk_conta_papeis_hist_papel FOREIGN KEY (papel_id) REFERENCES papeis(id) ON DELETE CASCADE,
    CONSTRAINT fk_conta_papeis_hist_realizado_por FOREIGN KEY (realizado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contas_acessos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NOT NULL,
    ip_usuario VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    caminho VARCHAR(255) NULL,
    session_id VARCHAR(128) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contas_acessos_conta (conta_id, created_at),
    INDEX idx_contas_acessos_ip (ip_usuario, created_at),
    CONSTRAINT fk_contas_acessos_conta FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vinculos_responsaveis (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dependente_pessoa_id BIGINT UNSIGNED NOT NULL UNIQUE,
    responsavel_pessoa_id BIGINT UNSIGNED NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    observacoes TEXT NULL,
    conta_criadora_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_vinculo_dependente FOREIGN KEY (dependente_pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_vinculo_responsavel FOREIGN KEY (responsavel_pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_vinculo_conta FOREIGN KEY (conta_criadora_id) REFERENCES contas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_transferencia_responsavel (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dependente_pessoa_id BIGINT UNSIGNED NOT NULL,
    antigo_responsavel_pessoa_id BIGINT UNSIGNED NOT NULL,
    novo_responsavel_pessoa_id BIGINT UNSIGNED NOT NULL,
    observacoes TEXT NULL,
    data_transferencia DATETIME NOT NULL,
    conta_criadora_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tipos_certificados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    nome VARCHAR(160) NOT NULL,
    categoria VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificados_pessoa (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    tipo_certificado_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pendente',
    descricao_resumida VARCHAR(255) NULL,
    codigo_cid_declarado VARCHAR(20) NULL,
    codigo_cid_validado VARCHAR(20) NULL,
    doenca_declarada VARCHAR(180) NULL,
    doenca_validada VARCHAR(180) NULL,
    tipos_deficiencia_pcd TEXT NULL,
    numero_nis VARCHAR(30) NULL,
    beneficio_social VARCHAR(180) NULL,
    data_emissao DATE NULL,
    validade_certificado DATE NULL,
    validado_por_conta_id BIGINT UNSIGNED NULL,
    validado_em DATETIME NULL,
    observacoes TEXT NULL,
    observacao_validacao TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_certificados_pessoa (pessoa_id, status),
    CONSTRAINT fk_certificados_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_certificados_tipo FOREIGN KEY (tipo_certificado_id) REFERENCES tipos_certificados(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documentos_certificados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificado_pessoa_id BIGINT UNSIGNED NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho_armazenado VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documentos_certificados FOREIGN KEY (certificado_pessoa_id) REFERENCES certificados_pessoa(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS atestados_saude (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    tipo_atestado ENUM('clinico', 'dermatologico') NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(255) NOT NULL,
    data_emissao DATE NULL,
    data_emissao_validada DATE NULL,
    validade_meses TINYINT UNSIGNED NULL,
    crm_medico VARCHAR(40) NULL,
    local_atendimento ENUM('servico_publico', 'clinica_particular', 'clinica_convenio') NULL,
    validade_certificado DATE NULL,
    status_validacao ENUM('pendente', 'validado', 'reprovado') NOT NULL DEFAULT 'pendente',
    validado_por_conta_id BIGINT UNSIGNED NULL,
    validado_em DATETIME NULL,
    observacoes TEXT NULL,
    observacao_validacao TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uniq_atestado_tipo (pessoa_id, tipo_atestado),
    CONSTRAINT fk_atestado_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS niveis_modalidade (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS modalidades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    tipo_ambiente ENUM('aquatica', 'terrestre') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificados_nivel_modalidade (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    modalidade_id BIGINT UNSIGNED NOT NULL,
    nivel_modalidade_id BIGINT UNSIGNED NOT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    validado_por_conta_id BIGINT UNSIGNED NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cert_nivel_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_cert_nivel_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id),
    CONSTRAINT fk_cert_nivel_nivel FOREIGN KEY (nivel_modalidade_id) REFERENCES niveis_modalidade(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS locais_treino (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    cep CHAR(8) NULL,
    logradouro VARCHAR(180) NULL,
    numero_endereco VARCHAR(20) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NOT NULL,
    uf CHAR(2) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_locais_treino_cep (cep),
    CONSTRAINT fk_locais_treino_admin_local FOREIGN KEY (admin_local) REFERENCES contas(id) ON DELETE SET NULL,
    CONSTRAINT fk_locais_treino_coord_local FOREIGN KEY (coord_local) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS locais_externos_vinculos (
    id_externo BIGINT UNSIGNED PRIMARY KEY,
    local_treino_id BIGINT UNSIGNED NOT NULL,
    vinculado_em DATETIME NOT NULL,
    UNIQUE KEY uk_local_externo_vinculo_atual (local_treino_id),
    CONSTRAINT fk_local_externo_vinculo_atual FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS espacos_treino (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    local_treino_id BIGINT UNSIGNED NOT NULL,
    supervisor_espaco BIGINT UNSIGNED NULL,
    nome VARCHAR(120) NOT NULL,
    tipo_espaco VARCHAR(80) NOT NULL,
    capacidade_base INT NOT NULL DEFAULT 0,
    acessibilidade_deficiencias_indisponiveis TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_espaco_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id),
    CONSTRAINT fk_espaco_supervisor FOREIGN KEY (supervisor_espaco) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS local_modalidade (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    local_treino_id BIGINT UNSIGNED NOT NULL,
    modalidade_id BIGINT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_local_modalidade (local_treino_id, modalidade_id),
    CONSTRAINT fk_local_modalidade_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id),
    CONSTRAINT fk_local_modalidade_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suspensoes_espaco_treino (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    espaco_treino_id BIGINT UNSIGNED NOT NULL,
    criado_por_conta_id BIGINT UNSIGNED NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    status ENUM('planejada', 'ativa', 'suspensa', 'encerrada', 'cancelada') NOT NULL DEFAULT 'planejada',
    motivo VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_suspensoes_espaco_treino (espaco_treino_id, data_inicio, data_fim, ativo),
    CONSTRAINT fk_suspensao_espaco FOREIGN KEY (espaco_treino_id) REFERENCES espacos_treino(id),
    CONSTRAINT fk_suspensao_espaco_criador FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS temporadas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    origem_temporada VARCHAR(180) NOT NULL,
    possui_edital TINYINT(1) NOT NULL DEFAULT 0,
    numero_edital VARCHAR(100) NULL,
    link_edital VARCHAR(2048) NULL,
    tipo_periodicidade ENUM('anual', 'semestral', 'quadrimestral', 'bimestral', 'mensal') NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    inscricoes_inicio DATETIME NULL,
    inscricoes_fim DATETIME NULL,
    matriculas_inicio DATETIME NULL,
    matriculas_fim DATETIME NULL,
    inscricoes_abertas_inicio DATETIME NULL,
    inscricoes_abertas_fim DATETIME NULL,
    aulas_inicio DATE NULL,
    aulas_fim DATE NULL,
    permitir_inscricao_por_cpf TINYINT(1) NOT NULL DEFAULT 0,
    permitir_inscricao_logada TINYINT(1) NOT NULL DEFAULT 1,
    limite_inscricoes_periodo INT UNSIGNED NOT NULL DEFAULT 1,
    data_liberacao_segunda_inscricao DATETIME NULL,
    data_liberacao_inscricoes_adicionais DATETIME NULL,
    limite_inscricoes_adicionais INT UNSIGNED NOT NULL DEFAULT 3,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS turmas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    temporada_id BIGINT UNSIGNED NOT NULL,
    modalidade_id BIGINT UNSIGNED NOT NULL,
    local_treino_id BIGINT UNSIGNED NOT NULL,
    espaco_treino_id BIGINT UNSIGNED NOT NULL,
    nivel_modalidade_id BIGINT UNSIGNED NULL,
    nome VARCHAR(160) NOT NULL,
    dias_semana VARCHAR(120) NULL,
    hora_inicio TIME NULL,
    hora_fim TIME NULL,
    idade_minima INT NOT NULL DEFAULT 0,
    idade_maxima INT NOT NULL DEFAULT 120,
    criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata',
    sexo ENUM('masculino', 'feminino') NULL,
    vagas_totais INT NOT NULL DEFAULT 0,
    vagas_geral INT NOT NULL DEFAULT 0,
    vagas_pcd INT NOT NULL DEFAULT 0,
    vagas_plm INT NOT NULL DEFAULT 0,
    vagas_pvs INT NOT NULL DEFAULT 0,
    vagas_espera_geral INT NOT NULL DEFAULT 0,
    vagas_espera_pcd INT NOT NULL DEFAULT 0,
    vagas_espera_plm INT NOT NULL DEFAULT 0,
    vagas_espera_pvs INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_turmas_temporada FOREIGN KEY (temporada_id) REFERENCES temporadas(id),
    CONSTRAINT fk_turmas_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id),
    CONSTRAINT fk_turmas_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id),
    CONSTRAINT fk_turmas_espaco FOREIGN KEY (espaco_treino_id) REFERENCES espacos_treino(id),
    CONSTRAINT fk_turmas_nivel FOREIGN KEY (nivel_modalidade_id) REFERENCES niveis_modalidade(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS avaliacoes_fisicas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    modalidade_id BIGINT UNSIGNED NOT NULL,
    data_avaliacao DATETIME NOT NULL,
    situacao ENUM('apto', 'nao_apto', 'pendente') NOT NULL DEFAULT 'pendente',
    nivel_modalidade_id BIGINT UNSIGNED NULL,
    observacoes TEXT NULL,
    validado_por_conta_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_avaliacao_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_avaliacao_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS horarios_semanais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    local_treino_id BIGINT UNSIGNED NOT NULL,
    espaco_treino_id BIGINT UNSIGNED NOT NULL,
    modalidade_id BIGINT UNSIGNED NOT NULL,
    tipo_horario ENUM('avaliacao', 'treino', 'aula') NOT NULL,
    dia_semana TINYINT UNSIGNED NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    idade_minima INT NOT NULL DEFAULT 0,
    idade_maxima INT NOT NULL DEFAULT 120,
    criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata',
    regra_atestado_clinico ENUM('global', 'exigir', 'dispensar') NOT NULL DEFAULT 'global',
    regra_atestado_dermatologico ENUM('global', 'exigir', 'dispensar') NOT NULL DEFAULT 'global',
    sexo ENUM('masculino', 'feminino') NULL,
    vagas_geral INT NOT NULL DEFAULT 0,
    vagas_pcd INT NOT NULL DEFAULT 0,
    vagas_plm INT NOT NULL DEFAULT 0,
    vagas_pvs INT NOT NULL DEFAULT 0,
    janela_agendamento_tipo ENUM('semana_atual_proxima', 'janela_semanal_fixa', 'antecedencia') NOT NULL DEFAULT 'semana_atual_proxima',
    janela_abertura_dia_semana TINYINT UNSIGNED NULL,
    janela_abertura_hora TIME NULL,
    janela_fechamento_dia_semana TINYINT UNSIGNED NULL,
    janela_fechamento_hora TIME NULL,
    janela_dias_antecedencia INT NOT NULL DEFAULT 7,
    janela_horas_antes_fechamento INT NOT NULL DEFAULT 2,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_inativacao DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_horarios_semanais (dia_semana, hora_inicio, ativo),
    CONSTRAINT fk_horario_local FOREIGN KEY (local_treino_id) REFERENCES locais_treino(id),
    CONSTRAINT fk_horario_espaco FOREIGN KEY (espaco_treino_id) REFERENCES espacos_treino(id),
    CONSTRAINT fk_horario_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agendamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    horario_semanal_id BIGINT UNSIGNED NOT NULL,
    data_agendada DATETIME NOT NULL,
    publico_alvo ENUM('geral', 'pcd', 'plm', 'pvs') NOT NULL DEFAULT 'geral',
    status ENUM('agendado', 'cancelado', 'presente', 'falta', 'justificado') NOT NULL DEFAULT 'agendado',
    chamada_por_conta_id BIGINT UNSIGNED NULL,
    justificativa_motivo VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_agendamentos_pessoa_data (pessoa_id, data_agendada, status),
    CONSTRAINT fk_agendamento_chamada_conta FOREIGN KEY (chamada_por_conta_id) REFERENCES contas(id),
    CONSTRAINT fk_agendamento_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_agendamento_horario FOREIGN KEY (horario_semanal_id) REFERENCES horarios_semanais(id)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS inscricoes_turma (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turma_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    publico_alvo ENUM('geral', 'pcd', 'plm', 'pvs') NOT NULL DEFAULT 'geral',
    status ENUM('aguardando_matricula', 'matriculada', 'lista_espera', 'cancelada', 'excluida', 'excluida_por_falta', 'desistente', 'suspensa') NOT NULL DEFAULT 'aguardando_matricula',
    inscrito_por_conta_id BIGINT UNSIGNED NULL,
    cancelado_por_conta_id BIGINT UNSIGNED NULL,
    motivo_status VARCHAR(255) NULL,
    suspensa_inicio DATETIME NULL,
    suspensa_fim DATETIME NULL,
    updated_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inscricao_turma FOREIGN KEY (turma_id) REFERENCES turmas(id),
    CONSTRAINT fk_inscricao_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
    CONSTRAINT fk_inscricao_criador FOREIGN KEY (inscrito_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    CONSTRAINT fk_inscricao_cancelador FOREIGN KEY (cancelado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    INDEX idx_inscricoes_turma_pessoa_status (turma_id, pessoa_id, status),
    INDEX idx_inscricoes_pessoa_status (pessoa_id, status)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS postagens_blog (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    categoria VARCHAR(120) NULL,
    tags VARCHAR(255) NULL,
    resumo TEXT NOT NULL,
    conteudo LONGTEXT NOT NULL,
    capa_imagem_url VARCHAR(255) NULL,
    status ENUM('rascunho', 'publicado') NOT NULL DEFAULT 'rascunho',
    destaque TINYINT(1) NOT NULL DEFAULT 0,
    publicar_na_home TINYINT(1) NOT NULL DEFAULT 0,
    permitir_compartilhamento TINYINT(1) NOT NULL DEFAULT 1,
    compartilhar_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
    compartilhar_facebook TINYINT(1) NOT NULL DEFAULT 1,
    compartilhar_linkedin TINYINT(1) NOT NULL DEFAULT 0,
    compartilhar_x TINYINT(1) NOT NULL DEFAULT 0,
    texto_compartilhamento VARCHAR(255) NULL,
    data_publicacao DATETIME NULL,
    publicado_em DATETIME NULL,
    criado_por_conta_id BIGINT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_postagens_blog_status_publicacao (status, data_publicacao, ativo),
    INDEX idx_postagens_blog_categoria (categoria, ativo),
    CONSTRAINT fk_postagem_conta FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blog_postagens_imagens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    postagem_blog_id BIGINT UNSIGNED NOT NULL,
    imagem_url VARCHAR(255) NOT NULL,
    legenda VARCHAR(255) NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blog_postagens_imagens_ordem (postagem_blog_id, ordem),
    CONSTRAINT fk_blog_postagens_imagens_postagem FOREIGN KEY (postagem_blog_id) REFERENCES postagens_blog(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_popups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NULL,
    texto_principal TEXT NULL,
    texto_secundario TEXT NULL,
    imagem_url VARCHAR(255) NULL,
    rotulo_acao VARCHAR(90) NULL,
    url_acao VARCHAR(255) NULL,
    caminhos_paginas TEXT NULL,
    mostrar_todas_paginas TINYINT(1) NOT NULL DEFAULT 0,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    status ENUM('ativo', 'arquivado', 'excluido') NOT NULL DEFAULT 'ativo',
    criado_por_conta_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_site_popups_status_datas (status, data_inicio, data_fim),
    CONSTRAINT fk_site_popup_conta FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS home_quadros_informativos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    titulo VARCHAR(70) NOT NULL,
    paragrafo_1 VARCHAR(110) NULL,
    paragrafo_1_link_rotulo VARCHAR(40) NULL,
    paragrafo_1_link_url VARCHAR(255) NULL,
    paragrafo_2 VARCHAR(110) NULL,
    paragrafo_2_link_rotulo VARCHAR(40) NULL,
    paragrafo_2_link_url VARCHAR(255) NULL,
    paragrafo_3 VARCHAR(110) NULL,
    paragrafo_3_link_rotulo VARCHAR(40) NULL,
    paragrafo_3_link_url VARCHAR(255) NULL,
    paragrafo_4 VARCHAR(110) NULL,
    paragrafo_4_link_rotulo VARCHAR(40) NULL,
    paragrafo_4_link_url VARCHAR(255) NULL,
    paragrafo_5 VARCHAR(110) NULL,
    paragrafo_5_link_rotulo VARCHAR(40) NULL,
    paragrafo_5_link_url VARCHAR(255) NULL,
    atualizado_por_conta_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_home_quadro_conta FOREIGN KEY (atualizado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS home_conteudos_configurados (
    chave VARCHAR(60) PRIMARY KEY,
    conteudo_json LONGTEXT NULL,
    rascunho_json LONGTEXT NULL,
    atualizado_por_conta_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_home_conteudo_conta FOREIGN KEY (atualizado_por_conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceps_excecao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cep CHAR(8) NOT NULL UNIQUE,
    observacoes VARCHAR(255) NULL,
    criado_por_conta_id BIGINT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_cep_excecao_conta FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ceps_intervalo_aceito (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cep_inicio CHAR(8) NOT NULL,
    cep_fim CHAR(8) NOT NULL,
    observacoes VARCHAR(255) NULL,
    criado_por_conta_id BIGINT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_cep_intervalo_conta FOREIGN KEY (criado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logs_auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NULL,
    tipo_evento VARCHAR(120) NOT NULL,
    tipo_entidade VARCHAR(120) NOT NULL,
    entidade_id BIGINT NULL,
    payload_json JSON NULL,
    ip_usuario VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_entidade (tipo_entidade, entidade_id),
    INDEX idx_logs_evento (tipo_evento, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reversoes_logicas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    log_auditoria_id BIGINT UNSIGNED NULL,
    tipo_alvo VARCHAR(80) NOT NULL,
    alvo_id BIGINT UNSIGNED NOT NULL,
    tabela_alvo VARCHAR(100) NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    estado_anterior_json JSON NULL,
    caminho_quarentena VARCHAR(500) NULL,
    executado_por_conta_id BIGINT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reversao_logica_log (log_auditoria_id),
    INDEX idx_reversao_logica_alvo (tipo_alvo, alvo_id, ativo),
    CONSTRAINT fk_reversao_logica_log FOREIGN KEY (log_auditoria_id) REFERENCES logs_auditoria(id) ON DELETE SET NULL,
    CONSTRAINT fk_reversao_logica_conta FOREIGN KEY (executado_por_conta_id) REFERENCES contas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
