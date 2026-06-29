ALTER TABLE postagens_blog
    ADD COLUMN categoria VARCHAR(120) NULL AFTER slug,
    ADD COLUMN tags VARCHAR(255) NULL AFTER categoria,
    ADD COLUMN capa_imagem_url VARCHAR(255) NULL AFTER conteudo,
    ADD COLUMN status ENUM('rascunho', 'publicado') NOT NULL DEFAULT 'rascunho' AFTER capa_imagem_url,
    ADD COLUMN destaque TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN publicar_na_home TINYINT(1) NOT NULL DEFAULT 0 AFTER destaque,
    ADD COLUMN permitir_compartilhamento TINYINT(1) NOT NULL DEFAULT 1 AFTER publicar_na_home,
    ADD COLUMN compartilhar_whatsapp TINYINT(1) NOT NULL DEFAULT 1 AFTER permitir_compartilhamento,
    ADD COLUMN compartilhar_facebook TINYINT(1) NOT NULL DEFAULT 1 AFTER compartilhar_whatsapp,
    ADD COLUMN compartilhar_linkedin TINYINT(1) NOT NULL DEFAULT 0 AFTER compartilhar_facebook,
    ADD COLUMN compartilhar_x TINYINT(1) NOT NULL DEFAULT 0 AFTER compartilhar_linkedin,
    ADD COLUMN texto_compartilhamento VARCHAR(255) NULL AFTER compartilhar_x,
    ADD COLUMN data_publicacao DATETIME NULL AFTER texto_compartilhamento,
    ADD COLUMN publicado_em DATETIME NULL AFTER data_publicacao;

UPDATE postagens_blog
SET
    status = 'publicado',
    data_publicacao = COALESCE(data_publicacao, created_at),
    publicado_em = COALESCE(publicado_em, created_at)
WHERE ativo = 1;

CREATE INDEX idx_postagens_blog_status_publicacao ON postagens_blog (status, data_publicacao, ativo);
CREATE INDEX idx_postagens_blog_categoria ON postagens_blog (categoria, ativo);
