-- Executar separadamente, via CLI/gerenciador de banco, antes de publicar a página.
CREATE TABLE IF NOT EXISTS pagina_tutorial_equipe_config (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY COMMENT '1: admin; 2: professor',
    titulo VARCHAR(180) NOT NULL,
    texto_introdutorio TEXT NULL,
    videos_json LONGTEXT NOT NULL,
    atualizado_por_conta_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO pagina_tutorial_equipe_config (id,titulo,texto_introdutorio,videos_json) VALUES
(1,'Ajuda e tutoriais do administrador','Assista aos tutoriais para configurar temporadas, modalidades, cronogramas e turmas.','[]'),
(2,'Ajuda e tutoriais do professor','Assista aos tutoriais para criar modalidades, cronogramas e turmas. A criação de temporadas é feita pela administração.','[]');
