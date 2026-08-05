-- Estrutura temporária. Remover ao concluir a migração de todos os atestados externos.
CREATE TABLE IF NOT EXISTS atestados_saude_importados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_atestado ENUM('clinico', 'dermatologico') NOT NULL,
    cpf CHAR(11) NOT NULL,
    pessoa_id BIGINT UNSIGNED NULL,
    id_externo BIGINT UNSIGNED NOT NULL,
    pessoa_id_externa BIGINT UNSIGNED NULL,
    usuario_id_externo BIGINT UNSIGNED NULL,
    data_emissao DATE NULL,
    validade_certificado DATE NULL,
    observacoes VARCHAR(500) NULL,
    data_atualizacao_origem DATETIME NULL,
    status_importacao ENUM('ativo', 'substituido') NOT NULL DEFAULT 'ativo',
    importado_por_conta_id BIGINT UNSIGNED NULL,
    importado_em DATETIME NOT NULL,
    substituido_por_atestado_id BIGINT UNSIGNED NULL,
    substituido_por_conta_id BIGINT UNSIGNED NULL,
    substituido_em DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_atestado_importado_cpf_tipo (cpf, tipo_atestado),
    INDEX idx_atestado_importado_pessoa_tipo (pessoa_id, tipo_atestado, status_importacao),
    INDEX idx_atestado_importado_validade (validade_certificado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importacoes_atestados_externos (
    tipo_atestado ENUM('clinico', 'dermatologico') PRIMARY KEY,
    ultima_data_origem DATETIME NOT NULL,
    ultimo_id_origem BIGINT UNSIGNED NOT NULL DEFAULT 0,
    concluido_em DATETIME NOT NULL,
    atualizado_por_conta_id BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
