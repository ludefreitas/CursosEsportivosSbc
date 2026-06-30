<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class OfficialCommunicationService
{
    private static bool $schemaChecked = false;
    public const SLUG_BLOG = 'blog-comunicacao-oficial';
    public const MAX_LABEL_LENGTH = 90;
    public const MAX_TITLE_LENGTH = 160;
    public const MAX_TEXT_LENGTH = 500;
    public const MAX_LINK_TITLE_LENGTH = 90;
    public const MAX_LINK_URL_LENGTH = 255;

    public function getBlogBlock(): array
    {
        $this->ensureSchema();

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT *
            FROM comunicacoes_oficiais
            WHERE slug = :slug
            LIMIT 1
        ');
        $stmt->execute([':slug' => self::SLUG_BLOG]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $this->defaultBlogBlock();
        }

        return [
            'slug' => (string) ($row['slug'] ?? self::SLUG_BLOG),
            'nome_quadro' => trim((string) ($row['nome_quadro'] ?? '')) ?: 'Comunicacao oficial',
            'titulo' => trim((string) ($row['titulo'] ?? '')),
            'texto_breve' => trim((string) ($row['texto_breve'] ?? '')),
            'link_url' => trim((string) ($row['link_url'] ?? '')),
            'link_titulo' => trim((string) ($row['link_titulo'] ?? '')),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function saveBlogBlock(int $accountId, array $data): array
    {
        $this->ensureSchema();

        $block = $this->validatePayload($data);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO comunicacoes_oficiais (
                slug,
                nome_quadro,
                titulo,
                texto_breve,
                link_url,
                link_titulo,
                atualizado_por_conta_id,
                updated_at
            ) VALUES (
                :slug,
                :nome_quadro,
                :titulo,
                :texto_breve,
                :link_url,
                :link_titulo,
                :atualizado_por_conta_id,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                nome_quadro = VALUES(nome_quadro),
                titulo = VALUES(titulo),
                texto_breve = VALUES(texto_breve),
                link_url = VALUES(link_url),
                link_titulo = VALUES(link_titulo),
                atualizado_por_conta_id = VALUES(atualizado_por_conta_id),
                updated_at = NOW()
        ');
        $stmt->execute([
            ':slug' => self::SLUG_BLOG,
            ':nome_quadro' => $block['nome_quadro'],
            ':titulo' => $block['titulo'],
            ':texto_breve' => $block['texto_breve'],
            ':link_url' => $block['link_url'] !== '' ? $block['link_url'] : null,
            ':link_titulo' => $block['link_titulo'] !== '' ? $block['link_titulo'] : null,
            ':atualizado_por_conta_id' => $accountId,
        ]);

        AuditLogService::record('home.comunicacao_oficial_salva', 'comunicacoes_oficiais', null, [
            'slug' => self::SLUG_BLOG,
            'nome_quadro' => $block['nome_quadro'],
            'titulo' => $block['titulo'],
            'possui_link' => $block['link_url'] !== '',
        ]);

        return $this->getBlogBlock();
    }

    private function defaultBlogBlock(): array
    {
        return [
            'slug' => self::SLUG_BLOG,
            'nome_quadro' => 'Comunicacao oficial',
            'titulo' => 'Blog dos Cursos Esportivos SBC',
            'texto_breve' => 'Noticias, campanhas, avisos e conteudos institucionais em uma pagina inspirada em blog classico, mas adaptada ao nosso portal e ao nosso fluxo administrativo.',
            'link_url' => '',
            'link_titulo' => '',
            'updated_at' => '',
        ];
    }

    private function validatePayload(array $data): array
    {
        $label = trim((string) ($data['nome_quadro'] ?? ''));
        $title = trim((string) ($data['titulo'] ?? ''));
        $text = trim((string) ($data['texto_breve'] ?? ''));
        $linkUrl = trim((string) ($data['link_url'] ?? ''));
        $linkTitle = trim((string) ($data['link_titulo'] ?? ''));

        if ($label === '') {
            throw new RuntimeException('Informe o nome do quadro de comunicacao oficial.');
        }

        if ($title === '') {
            throw new RuntimeException('Informe o titulo da comunicacao oficial.');
        }

        if ($text === '') {
            throw new RuntimeException('Informe o texto breve da comunicacao oficial.');
        }

        if (mb_strlen($label, 'UTF-8') > self::MAX_LABEL_LENGTH) {
            throw new RuntimeException('O nome do quadro deve ter no maximo ' . self::MAX_LABEL_LENGTH . ' caracteres.');
        }

        if (mb_strlen($title, 'UTF-8') > self::MAX_TITLE_LENGTH) {
            throw new RuntimeException('O titulo deve ter no maximo ' . self::MAX_TITLE_LENGTH . ' caracteres.');
        }

        if (mb_strlen($text, 'UTF-8') > self::MAX_TEXT_LENGTH) {
            throw new RuntimeException('O texto breve deve ter no maximo ' . self::MAX_TEXT_LENGTH . ' caracteres.');
        }

        if ($linkTitle !== '' && mb_strlen($linkTitle, 'UTF-8') > self::MAX_LINK_TITLE_LENGTH) {
            throw new RuntimeException('O titulo do link deve ter no maximo ' . self::MAX_LINK_TITLE_LENGTH . ' caracteres.');
        }

        if ($linkUrl !== '' && mb_strlen($linkUrl, 'UTF-8') > self::MAX_LINK_URL_LENGTH) {
            throw new RuntimeException('A URL do link deve ter no maximo ' . self::MAX_LINK_URL_LENGTH . ' caracteres.');
        }

        if (($linkTitle === '') !== ($linkUrl === '')) {
            throw new RuntimeException('Preencha o titulo e a URL do link juntos, ou deixe ambos em branco.');
        }

        if ($linkUrl !== '') {
            $linkUrl = $this->normalizeLinkUrl($linkUrl);
        }

        return [
            'nome_quadro' => $label,
            'titulo' => $title,
            'texto_breve' => $text,
            'link_url' => $linkUrl,
            'link_titulo' => $linkTitle,
        ];
    }

    private function normalizeLinkUrl(string $url): string
    {
        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Informe uma URL valida para a comunicacao oficial.');
        }

        return $url;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        $pdo = Database::connection();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS comunicacoes_oficiais (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(120) NOT NULL,
                nome_quadro VARCHAR(90) NOT NULL,
                titulo VARCHAR(160) NOT NULL,
                texto_breve TEXT NOT NULL,
                link_url VARCHAR(255) NULL,
                link_titulo VARCHAR(90) NULL,
                atualizado_por_conta_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_comunicacoes_oficiais_slug (slug),
                INDEX idx_comunicacoes_oficiais_conta (atualizado_por_conta_id)
            ) ENGINE=InnoDB
        ');

        self::$schemaChecked = true;
    }
}
