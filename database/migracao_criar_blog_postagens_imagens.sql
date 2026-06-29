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
