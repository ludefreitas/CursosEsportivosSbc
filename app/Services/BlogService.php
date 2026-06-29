<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class BlogService
{
    private static bool $schemaChecked = false;
    private const DEFAULT_COVER_IMAGE = '/assets/img/cursosesportivossbc.jpg';
    private const BLOG_COVER_UPLOAD_DIR = '/assets/img/blog/uploads';
    private const BLOG_GALLERY_UPLOAD_DIR = '/assets/img/blog/galeria';

    /**
     * Lista postagens publicadas para a area publica.
     */
    public function listPublishedPosts(array $filters = []): array
    {
        $this->ensureSchema();

        $limit = max(1, min(24, (int) ($filters['limit'] ?? 12)));
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $featuredOnly = (int) ($filters['featured_only'] ?? 0) === 1;

        $pdo = Database::connection();
        $sql = '
            SELECT
                pb.*,
                p.nome_completo AS autor_nome,
                COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) AS data_publica_ordenacao
            FROM postagens_blog pb
            INNER JOIN contas c ON c.id = pb.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE pb.ativo = 1
              AND pb.status = :status
              AND COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) <= NOW()
        ';
        $params = [
            ':status' => 'publicado',
        ];

        if ($search !== '') {
            $sql .= ' AND (pb.titulo LIKE :busca_titulo OR pb.resumo LIKE :busca_resumo OR pb.conteudo LIKE :busca_conteudo OR pb.tags LIKE :busca_tags) ';
            $searchLike = '%' . $search . '%';
            $params[':busca_titulo'] = $searchLike;
            $params[':busca_resumo'] = $searchLike;
            $params[':busca_conteudo'] = $searchLike;
            $params[':busca_tags'] = $searchLike;
        }

        if ($category !== '') {
            $sql .= ' AND pb.categoria = :categoria ';
            $params[':categoria'] = $category;
        }

        if ($featuredOnly) {
            $sql .= ' AND pb.destaque = 1 ';
        }

        $sql .= '
            ORDER BY pb.destaque DESC, data_publica_ordenacao DESC, pb.id DESC
            LIMIT :limite
        ';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $post): array => $this->hydratePublicPost($post), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Lista postagens para gestao administrativa.
     */
    public function listPostsForAdmin(): array
    {
        $this->ensureSchema();

        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT
                pb.*,
                p.nome_completo AS autor_nome,
                COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) AS data_publica_ordenacao
            FROM postagens_blog pb
            INNER JOIN contas c ON c.id = pb.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE pb.ativo = 1
            ORDER BY data_publica_ordenacao DESC, pb.id DESC
        ');

        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $post): array {
            $post['tags_array'] = $this->parseTags($post['tags'] ?? '');
            $post['share_channels_label'] = implode(', ', $this->availableShareChannelLabels($post));
            $post['public_url'] = url('/blog/post?slug=' . rawurlencode((string) ($post['slug'] ?? '')));
            $post['gallery_images'] = $this->loadGalleryImages((int) ($post['id'] ?? 0));
            return $post;
        }, $posts);
    }

    /**
     * Retorna uma postagem especifica para o admin.
     */
    public function getPostForAdmin(int $postId): array
    {
        $this->ensureSchema();

        if ($postId <= 0) {
            throw new RuntimeException('Postagem invalida.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT pb.*
            FROM postagens_blog pb
            WHERE pb.id = :id
              AND pb.ativo = 1
            LIMIT 1
        ');
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            throw new RuntimeException('Postagem nao encontrada.');
        }

        $post['tags_array'] = $this->parseTags($post['tags'] ?? '');
        $post['share_links'] = $this->buildShareLinks($post);
        $post['public_url'] = url('/blog/post?slug=' . rawurlencode((string) ($post['slug'] ?? '')));
        $post['gallery_images'] = $this->loadGalleryImages($postId);

        return $post;
    }

    /**
     * Retorna uma postagem publica pelo slug.
     */
    public function findPublishedPostBySlug(string $slug): ?array
    {
        $this->ensureSchema();

        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                pb.*,
                p.nome_completo AS autor_nome,
                COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) AS data_publica_ordenacao
            FROM postagens_blog pb
            INNER JOIN contas c ON c.id = pb.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE pb.slug = :slug
              AND pb.ativo = 1
              AND pb.status = :status
              AND COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) <= NOW()
            LIMIT 1
        ');
        $stmt->execute([
            ':slug' => $slug,
            ':status' => 'publicado',
        ]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        return $post ? $this->hydratePublicPost($post) : null;
    }

    /**
     * Salva uma postagem, criando ou atualizando conforme o caso.
     */
    public function savePost(int $accountId, array $data, array $files = []): array
    {
        $this->ensureSchema();

        $postId = (int) ($data['post_id'] ?? 0);
        $title = trim((string) ($data['titulo'] ?? ''));
        $summary = trim((string) ($data['resumo'] ?? ''));
        $content = trim((string) ($data['conteudo'] ?? ''));
        $category = trim((string) ($data['categoria'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'rascunho'));
        $customSlug = trim((string) ($data['slug'] ?? ''));
        $shareText = trim((string) ($data['texto_compartilhamento'] ?? ''));
        $publishAt = $this->normalizePublicationDate((string) ($data['data_publicacao'] ?? ''));
        $tags = $this->normalizeTags((string) ($data['tags'] ?? ''));
        $existingCoverImageUrl = trim((string) ($data['capa_imagem_atual'] ?? ''));
        $imageUrl = $this->resolveCoverImage($files['capa_imagem_arquivo'] ?? null, $existingCoverImageUrl, $title);
        $galleryImages = $this->normalizeGalleryItems(
            $data['galeria_imagem_atual'] ?? [],
            $data['galeria_imagem_legenda'] ?? [],
            $files['galeria_imagem_arquivo'] ?? null
        );
        $highlight = $this->checkboxValue($data, 'destaque');
        $publishOnHome = $this->checkboxValue($data, 'publicar_na_home');
        $allowShare = $this->checkboxValue($data, 'permitir_compartilhamento');
        $shareWhatsapp = $allowShare ? $this->checkboxValue($data, 'compartilhar_whatsapp') : 0;
        $shareFacebook = $allowShare ? $this->checkboxValue($data, 'compartilhar_facebook') : 0;
        $shareLinkedin = $allowShare ? $this->checkboxValue($data, 'compartilhar_linkedin') : 0;
        $shareX = $allowShare ? $this->checkboxValue($data, 'compartilhar_x') : 0;

        if ($title === '' || $summary === '' || $content === '') {
            throw new RuntimeException('Preencha titulo, resumo e conteudo da postagem.');
        }

        if (!in_array($status, ['rascunho', 'publicado'], true)) {
            throw new RuntimeException('Selecione um status valido para a postagem.');
        }

        if (mb_strlen($title, 'UTF-8') > 180) {
            throw new RuntimeException('O titulo pode ter no maximo 180 caracteres.');
        }

        if (mb_strlen($category, 'UTF-8') > 120) {
            throw new RuntimeException('A categoria pode ter no maximo 120 caracteres.');
        }

        if (mb_strlen($shareText, 'UTF-8') > 255) {
            throw new RuntimeException('O texto de compartilhamento pode ter no maximo 255 caracteres.');
        }

        if ($imageUrl === '') {
            $imageUrl = $this->defaultCoverImage();
        }

        $slug = $this->generateUniqueSlug($customSlug !== '' ? $customSlug : $title, $postId > 0 ? $postId : null);

        $pdo = Database::connection();
        $existing = $postId > 0 ? $this->getPostForAdmin($postId) : null;
        $publishedAt = null;

        if ($status === 'publicado') {
            $publishedAt = $existing['publicado_em'] ?? null;

            if ($publishedAt === null || trim((string) $publishedAt) === '') {
                $publishedAt = date('Y-m-d H:i:s');
            }

            if ($publishAt === null) {
                $publishAt = date('Y-m-d H:i:s');
            }
        }

        if ($postId > 0) {
            $stmt = $pdo->prepare('
                UPDATE postagens_blog
                SET
                    titulo = :titulo,
                    slug = :slug,
                    categoria = :categoria,
                    tags = :tags,
                    resumo = :resumo,
                    conteudo = :conteudo,
                    capa_imagem_url = :capa_imagem_url,
                    status = :status,
                    destaque = :destaque,
                    publicar_na_home = :publicar_na_home,
                    permitir_compartilhamento = :permitir_compartilhamento,
                    compartilhar_whatsapp = :compartilhar_whatsapp,
                    compartilhar_facebook = :compartilhar_facebook,
                    compartilhar_linkedin = :compartilhar_linkedin,
                    compartilhar_x = :compartilhar_x,
                    texto_compartilhamento = :texto_compartilhamento,
                    data_publicacao = :data_publicacao,
                    publicado_em = :publicado_em,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute([
                ':titulo' => $title,
                ':slug' => $slug,
                ':categoria' => $category !== '' ? $category : null,
                ':tags' => $tags !== '' ? $tags : null,
                ':resumo' => $summary,
                ':conteudo' => $content,
                ':capa_imagem_url' => $imageUrl !== '' ? $imageUrl : null,
                ':status' => $status,
                ':destaque' => $highlight,
                ':publicar_na_home' => $publishOnHome,
                ':permitir_compartilhamento' => $allowShare,
                ':compartilhar_whatsapp' => $shareWhatsapp,
                ':compartilhar_facebook' => $shareFacebook,
                ':compartilhar_linkedin' => $shareLinkedin,
                ':compartilhar_x' => $shareX,
                ':texto_compartilhamento' => $shareText !== '' ? $shareText : null,
                ':data_publicacao' => $publishAt,
                ':publicado_em' => $publishedAt,
                ':id' => $postId,
            ]);

            AuditLogService::record('blog.postagem_atualizada', 'postagens_blog', $postId, [
                'titulo' => $title,
                'status' => $status,
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO postagens_blog (
                    titulo,
                    slug,
                    categoria,
                    tags,
                    resumo,
                    conteudo,
                    capa_imagem_url,
                    status,
                    destaque,
                    publicar_na_home,
                    permitir_compartilhamento,
                    compartilhar_whatsapp,
                    compartilhar_facebook,
                    compartilhar_linkedin,
                    compartilhar_x,
                    texto_compartilhamento,
                    data_publicacao,
                    publicado_em,
                    criado_por_conta_id,
                    ativo
                ) VALUES (
                    :titulo,
                    :slug,
                    :categoria,
                    :tags,
                    :resumo,
                    :conteudo,
                    :capa_imagem_url,
                    :status,
                    :destaque,
                    :publicar_na_home,
                    :permitir_compartilhamento,
                    :compartilhar_whatsapp,
                    :compartilhar_facebook,
                    :compartilhar_linkedin,
                    :compartilhar_x,
                    :texto_compartilhamento,
                    :data_publicacao,
                    :publicado_em,
                    :criado_por_conta_id,
                    1
                )
            ');
            $stmt->execute([
                ':titulo' => $title,
                ':slug' => $slug,
                ':categoria' => $category !== '' ? $category : null,
                ':tags' => $tags !== '' ? $tags : null,
                ':resumo' => $summary,
                ':conteudo' => $content,
                ':capa_imagem_url' => $imageUrl !== '' ? $imageUrl : null,
                ':status' => $status,
                ':destaque' => $highlight,
                ':publicar_na_home' => $publishOnHome,
                ':permitir_compartilhamento' => $allowShare,
                ':compartilhar_whatsapp' => $shareWhatsapp,
                ':compartilhar_facebook' => $shareFacebook,
                ':compartilhar_linkedin' => $shareLinkedin,
                ':compartilhar_x' => $shareX,
                ':texto_compartilhamento' => $shareText !== '' ? $shareText : null,
                ':data_publicacao' => $publishAt,
                ':publicado_em' => $publishedAt,
                ':criado_por_conta_id' => $accountId,
            ]);

            $postId = (int) $pdo->lastInsertId();

            AuditLogService::record('blog.postagem_criada', 'postagens_blog', $postId, [
                'titulo' => $title,
                'status' => $status,
            ]);
        }

        $this->replaceGalleryImages($postId, $galleryImages);

        return $this->getPostForAdmin($postId);
    }

    /**
     * Inativa uma postagem.
     */
    public function deletePost(int $postId): void
    {
        $this->ensureSchema();

        if ($postId <= 0) {
            throw new RuntimeException('Postagem invalida.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE postagens_blog
            SET ativo = 0, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([':id' => $postId]);

        AuditLogService::record('blog.postagem_removida', 'postagens_blog', $postId, []);
    }

    /**
     * Lista categorias publicas com contagem.
     */
    public function listPublicCategories(): array
    {
        $this->ensureSchema();

        $pdo = Database::connection();
        $stmt = $pdo->query("
            SELECT categoria, COUNT(*) AS total
            FROM postagens_blog
            WHERE ativo = 1
              AND status = 'publicado'
              AND categoria IS NOT NULL
              AND categoria <> ''
              AND COALESCE(data_publicacao, publicado_em, created_at) <= NOW()
            GROUP BY categoria
            ORDER BY total DESC, categoria ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista o arquivo mensal do blog.
     */
    public function listArchiveMonths(): array
    {
        $this->ensureSchema();

        $pdo = Database::connection();
        $stmt = $pdo->query("
            SELECT
                DATE_FORMAT(COALESCE(data_publicacao, publicado_em, created_at), '%Y-%m') AS chave,
                DATE_FORMAT(COALESCE(data_publicacao, publicado_em, created_at), '%m/%Y') AS rotulo,
                COUNT(*) AS total
            FROM postagens_blog
            WHERE ativo = 1
              AND status = 'publicado'
              AND COALESCE(data_publicacao, publicado_em, created_at) <= NOW()
            GROUP BY chave, rotulo
            ORDER BY chave DESC
            LIMIT 12
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista postagens relacionadas.
     */
    public function listRelatedPosts(array $post, int $limit = 3): array
    {
        $this->ensureSchema();

        $pdo = Database::connection();
        $limit = max(1, min(6, $limit));
        $category = trim((string) ($post['categoria'] ?? ''));
        $id = (int) ($post['id'] ?? 0);

        if ($category !== '') {
            $stmt = $pdo->prepare("
                SELECT
                    pb.*,
                    p.nome_completo AS autor_nome,
                    COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) AS data_publica_ordenacao
                FROM postagens_blog pb
                INNER JOIN contas c ON c.id = pb.criado_por_conta_id
                INNER JOIN pessoas p ON p.cpf = c.cpf
                WHERE pb.ativo = 1
                  AND pb.status = 'publicado'
                  AND pb.id <> :id
                  AND pb.categoria = :categoria
                  AND COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) <= NOW()
                ORDER BY pb.destaque DESC, data_publica_ordenacao DESC
                LIMIT :limite
            ");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':categoria', $category);
            $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows !== []) {
                return array_map(fn (array $item): array => $this->hydratePublicPost($item), $rows);
            }
        }

        $stmt = $pdo->prepare("
            SELECT
                pb.*,
                p.nome_completo AS autor_nome,
                COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) AS data_publica_ordenacao
            FROM postagens_blog pb
            INNER JOIN contas c ON c.id = pb.criado_por_conta_id
            INNER JOIN pessoas p ON p.cpf = c.cpf
            WHERE pb.ativo = 1
              AND pb.status = 'publicado'
              AND pb.id <> :id
              AND COALESCE(pb.data_publicacao, pb.publicado_em, pb.created_at) <= NOW()
            ORDER BY pb.destaque DESC, data_publica_ordenacao DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $item): array => $this->hydratePublicPost($item), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Retorna um resumo numerico para o admin.
     */
    public function adminSummary(): array
    {
        $this->ensureSchema();

        $pdo = Database::connection();
        $stmt = $pdo->query("
            SELECT
                SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS total_ativos,
                SUM(CASE WHEN ativo = 1 AND status = 'publicado' THEN 1 ELSE 0 END) AS total_publicados,
                SUM(CASE WHEN ativo = 1 AND status = 'rascunho' THEN 1 ELSE 0 END) AS total_rascunhos,
                SUM(CASE WHEN ativo = 1 AND destaque = 1 THEN 1 ELSE 0 END) AS total_destaques
            FROM postagens_blog
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_ativos' => 0,
            'total_publicados' => 0,
            'total_rascunhos' => 0,
            'total_destaques' => 0,
        ];
    }

    /**
     * Hidrata uma postagem publica com dados de visualizacao.
     */
    private function hydratePublicPost(array $post): array
    {
        $post['tags_array'] = $this->parseTags($post['tags'] ?? '');
        $post['capa_imagem_url'] = trim((string) ($post['capa_imagem_url'] ?? '')) !== '' ? (string) $post['capa_imagem_url'] : $this->defaultCoverImage();
        $post['hero_background_url'] = $post['capa_imagem_url'];
        $post['gallery_images'] = $this->loadGalleryImages((int) ($post['id'] ?? 0));

        if ($post['gallery_images'] === []) {
            $post['gallery_images'] = [[
                'imagem_url' => $post['capa_imagem_url'],
                'legenda' => 'Imagem padrao da postagem',
                'ordem' => 1,
            ]];
        }

        $post['share_links'] = $this->buildShareLinks($post);
        $post['public_url'] = $this->absoluteUrl(url('/blog/post?slug=' . rawurlencode((string) ($post['slug'] ?? ''))));
        return $post;
    }

    /**
     * Lista as imagens extras da postagem.
     */
    private function loadGalleryImages(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT imagem_url, legenda, ordem
            FROM blog_postagens_imagens
            WHERE postagem_blog_id = :postagem_blog_id
            ORDER BY ordem ASC, id ASC
        ');
        $stmt->execute([':postagem_blog_id' => $postId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Substitui a galeria atual da postagem.
     */
    private function replaceGalleryImages(int $postId, array $galleryImages): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM blog_postagens_imagens WHERE postagem_blog_id = :postagem_blog_id')
            ->execute([':postagem_blog_id' => $postId]);

        if ($galleryImages === []) {
            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO blog_postagens_imagens (postagem_blog_id, imagem_url, legenda, ordem)
            VALUES (:postagem_blog_id, :imagem_url, :legenda, :ordem)
        ');

        foreach ($galleryImages as $index => $item) {
            $stmt->execute([
                ':postagem_blog_id' => $postId,
                ':imagem_url' => (string) $item['imagem_url'],
                ':legenda' => trim((string) ($item['legenda'] ?? '')) !== '' ? (string) $item['legenda'] : null,
                ':ordem' => $index + 1,
            ]);
        }
    }

    /**
     * Normaliza os itens da galeria do formulario.
     */
    private function normalizeGalleryItems($existingImageUrls, $captions, $galleryFiles): array
    {
        $existingImageUrls = is_array($existingImageUrls) ? $existingImageUrls : [];
        $captions = is_array($captions) ? $captions : [];
        $items = [];

        $rowCount = max(count($existingImageUrls), count($captions), $this->uploadedRowsCount($galleryFiles));

        for ($index = 0; $index < $rowCount; $index += 1) {
            $imageUrl = trim((string) ($existingImageUrls[$index] ?? ''));
            $caption = trim((string) ($captions[$index] ?? ''));
            $uploadedFile = $this->extractUploadedRow($galleryFiles, $index);

            if ($uploadedFile !== null) {
                $imageUrl = $this->storeUploadedImage($uploadedFile, self::BLOG_GALLERY_UPLOAD_DIR, 'galeria-' . ($index + 1));
            }

            if ($imageUrl === '') {
                continue;
            }

            $items[] = [
                'imagem_url' => $imageUrl,
                'legenda' => $caption,
            ];
        }

        return $items;
    }

    /**
     * Resolve a imagem de capa a partir do upload atual ou do valor ja salvo.
     */
    private function resolveCoverImage($uploadedFile, string $existingImageUrl, string $title): string
    {
        $file = $this->normalizeUploadedFile($uploadedFile);

        if ($file !== null) {
            return $this->storeUploadedImage($file, self::BLOG_COVER_UPLOAD_DIR, $title);
        }

        return $existingImageUrl;
    }

    /**
     * Retorna quantas linhas de upload vieram da galeria.
     */
    private function uploadedRowsCount($galleryFiles): int
    {
        if (!is_array($galleryFiles) || !isset($galleryFiles['name']) || !is_array($galleryFiles['name'])) {
            return 0;
        }

        return count($galleryFiles['name']);
    }

    /**
     * Extrai um arquivo enviado em uma posicao da galeria.
     */
    private function extractUploadedRow($galleryFiles, int $index): ?array
    {
        if (!is_array($galleryFiles) || !isset($galleryFiles['name']) || !is_array($galleryFiles['name'])) {
            return null;
        }

        return $this->normalizeUploadedFile([
            'name' => $galleryFiles['name'][$index] ?? '',
            'type' => $galleryFiles['type'][$index] ?? '',
            'tmp_name' => $galleryFiles['tmp_name'][$index] ?? '',
            'error' => $galleryFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $galleryFiles['size'][$index] ?? 0,
        ]);
    }

    /**
     * Normaliza um arquivo enviado simples.
     */
    private function normalizeUploadedFile($file): ?array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return null;
        }

        $error = (int) $file['error'];

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nao foi possivel concluir o upload da imagem selecionada.');
        }

        return $file;
    }

    /**
     * Salva uma imagem enviada em disco e devolve o caminho publico.
     */
    private function storeUploadedImage(array $file, string $publicDirectory, string $baseName): string
    {
        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new RuntimeException('A imagem enviada nao esta disponivel para processamento.');
        }

        $imageInfo = @getimagesize($tmpPath);

        if ($imageInfo === false) {
            throw new RuntimeException('O arquivo enviado nao e uma imagem valida.');
        }

        $mimeType = (string) ($imageInfo['mime'] ?? '');
        $allowedExtensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowedExtensions[$mimeType])) {
            throw new RuntimeException('Envie uma imagem JPG, PNG, WEBP ou GIF.');
        }

        $extension = $allowedExtensions[$mimeType];
        $relativeDirectory = '/public' . $publicDirectory;
        $absoluteDirectory = ROOT_PATH . $relativeDirectory;

        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('Nao foi possivel preparar a pasta de imagens do blog.');
        }

        $safeBaseName = slugify($baseName !== '' ? $baseName : pathinfo((string) ($file['name'] ?? 'imagem'), PATHINFO_FILENAME));
        $fileName = $safeBaseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        $absolutePath = $absoluteDirectory . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('Nao foi possivel salvar a imagem enviada.');
        }

        return $publicDirectory . '/' . $fileName;
    }

    /**
     * Retorna os links de compartilhamento habilitados.
     */
    private function buildShareLinks(array $post): array
    {
        $allowShare = (int) ($post['permitir_compartilhamento'] ?? 0) === 1;

        if (!$allowShare) {
            return [];
        }

        $title = (string) ($post['titulo'] ?? '');
        $summary = trim((string) ($post['texto_compartilhamento'] ?? '')) !== ''
            ? (string) $post['texto_compartilhamento']
            : (string) ($post['resumo'] ?? '');
        $publicUrl = $this->absoluteUrl(url('/blog/post?slug=' . rawurlencode((string) ($post['slug'] ?? ''))));

        $links = [
            'copiar' => $publicUrl,
        ];

        if ((int) ($post['compartilhar_whatsapp'] ?? 0) === 1) {
            $links['whatsapp'] = 'https://wa.me/?text=' . rawurlencode($title . ' - ' . $summary . ' ' . $publicUrl);
        }

        if ((int) ($post['compartilhar_facebook'] ?? 0) === 1) {
            $links['facebook'] = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($publicUrl);
        }

        if ((int) ($post['compartilhar_linkedin'] ?? 0) === 1) {
            $links['linkedin'] = 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($publicUrl);
        }

        if ((int) ($post['compartilhar_x'] ?? 0) === 1) {
            $links['x'] = 'https://twitter.com/intent/tweet?text=' . rawurlencode($title . ' - ' . $summary) . '&url=' . rawurlencode($publicUrl);
        }

        return $links;
    }

    /**
     * Lista os nomes dos canais de compartilhamento habilitados.
     */
    private function availableShareChannelLabels(array $post): array
    {
        if ((int) ($post['permitir_compartilhamento'] ?? 0) !== 1) {
            return ['Compartilhamento desligado'];
        }

        $labels = [];

        if ((int) ($post['compartilhar_whatsapp'] ?? 0) === 1) {
            $labels[] = 'WhatsApp';
        }

        if ((int) ($post['compartilhar_facebook'] ?? 0) === 1) {
            $labels[] = 'Facebook';
        }

        if ((int) ($post['compartilhar_linkedin'] ?? 0) === 1) {
            $labels[] = 'LinkedIn';
        }

        if ((int) ($post['compartilhar_x'] ?? 0) === 1) {
            $labels[] = 'X';
        }

        return $labels === [] ? ['Link direto'] : $labels;
    }

    /**
     * Normaliza a data de publicacao vinda do formulario.
     */
    private function normalizePublicationDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);

        if (strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        $timestamp = strtotime($normalized);

        if ($timestamp === false) {
            throw new RuntimeException('A data de publicacao informada e invalida.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Gera um slug unico.
     */
    private function generateUniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        $candidate = $slug;
        $suffix = 2;
        $pdo = Database::connection();

        while (true) {
            $sql = 'SELECT id FROM postagens_blog WHERE slug = :slug';
            $params = [':slug' => $candidate];

            if ($ignoreId !== null) {
                $sql .= ' AND id <> :id';
                $params[':id'] = $ignoreId;
            }

            $stmt = $pdo->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $candidate;
            }

            $candidate = $slug . '-' . $suffix;
            $suffix += 1;
        }
    }

    /**
     * Normaliza a lista de tags.
     */
    private function normalizeTags(string $value): string
    {
        $items = preg_split('/[,;]+/', $value) ?: [];
        $normalized = [];

        foreach ($items as $item) {
            $tag = trim((string) $item);

            if ($tag === '') {
                continue;
            }

            if (!in_array($tag, $normalized, true)) {
                $normalized[] = $tag;
            }
        }

        return implode(', ', $normalized);
    }

    /**
     * Converte a string de tags em array.
     */
    private function parseTags(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $items = preg_split('/[,;]+/', $value) ?: [];
        $tags = [];

        foreach ($items as $item) {
            $tag = trim((string) $item);

            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Lida com os checkboxes do formulario.
     */
    private function checkboxValue(array $data, string $key): int
    {
        return isset($data[$key]) && in_array((string) $data[$key], ['1', 'on', 'true'], true) ? 1 : 0;
    }

    /**
     * Retorna a URL absoluta para compartilhamento externo.
     */
    private function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host . $path;
    }

    /**
     * Caminho padrao para capa e fallback da postagem.
     */
    private function defaultCoverImage(): string
    {
        return self::DEFAULT_COVER_IMAGE;
    }

    /**
     * Garante que a tabela do blog esteja expandida com os campos novos.
     */
    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        $pdo = Database::connection();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM postagens_blog');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) $column['Field']] = true;
        }

        $alterations = [];
        $statusWasAdded = false;

        if (!isset($columns['categoria'])) {
            $alterations[] = 'ADD COLUMN categoria VARCHAR(120) NULL AFTER slug';
        }

        if (!isset($columns['tags'])) {
            $alterations[] = 'ADD COLUMN tags VARCHAR(255) NULL AFTER categoria';
        }

        if (!isset($columns['capa_imagem_url'])) {
            $alterations[] = 'ADD COLUMN capa_imagem_url VARCHAR(255) NULL AFTER conteudo';
        }

        if (!isset($columns['status'])) {
            $alterations[] = "ADD COLUMN status ENUM('rascunho', 'publicado') NOT NULL DEFAULT 'rascunho' AFTER capa_imagem_url";
            $statusWasAdded = true;
        }

        if (!isset($columns['destaque'])) {
            $alterations[] = 'ADD COLUMN destaque TINYINT(1) NOT NULL DEFAULT 0 AFTER status';
        }

        if (!isset($columns['publicar_na_home'])) {
            $alterations[] = 'ADD COLUMN publicar_na_home TINYINT(1) NOT NULL DEFAULT 0 AFTER destaque';
        }

        if (!isset($columns['permitir_compartilhamento'])) {
            $alterations[] = 'ADD COLUMN permitir_compartilhamento TINYINT(1) NOT NULL DEFAULT 1 AFTER publicar_na_home';
        }

        if (!isset($columns['compartilhar_whatsapp'])) {
            $alterations[] = 'ADD COLUMN compartilhar_whatsapp TINYINT(1) NOT NULL DEFAULT 1 AFTER permitir_compartilhamento';
        }

        if (!isset($columns['compartilhar_facebook'])) {
            $alterations[] = 'ADD COLUMN compartilhar_facebook TINYINT(1) NOT NULL DEFAULT 1 AFTER compartilhar_whatsapp';
        }

        if (!isset($columns['compartilhar_linkedin'])) {
            $alterations[] = 'ADD COLUMN compartilhar_linkedin TINYINT(1) NOT NULL DEFAULT 0 AFTER compartilhar_facebook';
        }

        if (!isset($columns['compartilhar_x'])) {
            $alterations[] = 'ADD COLUMN compartilhar_x TINYINT(1) NOT NULL DEFAULT 0 AFTER compartilhar_linkedin';
        }

        if (!isset($columns['texto_compartilhamento'])) {
            $alterations[] = 'ADD COLUMN texto_compartilhamento VARCHAR(255) NULL AFTER compartilhar_x';
        }

        if (!isset($columns['data_publicacao'])) {
            $alterations[] = 'ADD COLUMN data_publicacao DATETIME NULL AFTER texto_compartilhamento';
        }

        if (!isset($columns['publicado_em'])) {
            $alterations[] = 'ADD COLUMN publicado_em DATETIME NULL AFTER data_publicacao';
        }

        if ($alterations !== []) {
            $pdo->exec('ALTER TABLE postagens_blog ' . implode(', ', $alterations));
        }

        if ($statusWasAdded) {
            $pdo->exec("
                UPDATE postagens_blog
                SET
                    status = 'publicado',
                    data_publicacao = COALESCE(data_publicacao, created_at),
                    publicado_em = COALESCE(publicado_em, created_at)
                WHERE ativo = 1
            ");
        }

        $indexes = [];
        $indexRows = $pdo->query('SHOW INDEX FROM postagens_blog')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($indexRows as $row) {
            $indexes[(string) $row['Key_name']] = true;
        }

        if (!isset($indexes['idx_postagens_blog_status_publicacao'])) {
            $pdo->exec('CREATE INDEX idx_postagens_blog_status_publicacao ON postagens_blog (status, data_publicacao, ativo)');
        }

        if (!isset($indexes['idx_postagens_blog_categoria'])) {
            $pdo->exec('CREATE INDEX idx_postagens_blog_categoria ON postagens_blog (categoria, ativo)');
        }

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS blog_postagens_imagens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                postagem_blog_id BIGINT UNSIGNED NOT NULL,
                imagem_url VARCHAR(255) NOT NULL,
                legenda VARCHAR(255) NULL,
                ordem INT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_blog_postagens_imagens_postagem
                    FOREIGN KEY (postagem_blog_id) REFERENCES postagens_blog(id)
                    ON DELETE CASCADE,
                INDEX idx_blog_postagens_imagens_ordem (postagem_blog_id, ordem)
            ) ENGINE=InnoDB
        ');

        self::$schemaChecked = true;
    }
}
