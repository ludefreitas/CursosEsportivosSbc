USE cursos_esportivos_sbc;

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
