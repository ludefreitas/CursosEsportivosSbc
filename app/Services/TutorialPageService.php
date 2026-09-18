<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class TutorialPageService
{
    public const MAX_VIDEOS = 30;

    public function get(): array
    {
        $this->ensureSchema();
        $row = Database::connection()->query('SELECT * FROM pagina_tutorial_config WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $this->defaults();
        $videos = json_decode((string) ($row['videos_json'] ?? '[]'), true) ?: [];
        $row['videos'] = array_values(array_map(function (array $video): array {
            $video['embed_url'] = $this->youtubeEmbedUrl((string) ($video['url'] ?? ''));
            return $video;
        }, $videos));
        return $row;
    }

    public function save(int $accountId, array $data): array
    {
        $title = trim((string) ($data['titulo'] ?? ''));
        $intro = trim((string) ($data['texto_introdutorio'] ?? ''));
        if ($title === '') throw new RuntimeException('Informe o título principal da página de ajuda.');
        if (mb_strlen($title) > 180) throw new RuntimeException('O título principal deve ter no máximo 180 caracteres.');
        if (mb_strlen($intro) > 1500) throw new RuntimeException('O texto introdutório deve ter no máximo 1.500 caracteres.');

        $videoTitles = (array) ($data['video_titulo'] ?? []);
        $videoUrls = (array) ($data['video_url'] ?? []);
        $videos = [];
        for ($index = 0; $index < self::MAX_VIDEOS; $index++) {
            $videoTitle = trim((string) ($videoTitles[$index] ?? ''));
            $videoUrl = trim((string) ($videoUrls[$index] ?? ''));
            if ($videoTitle === '' && $videoUrl === '') continue;
            if ($videoTitle === '' || $videoUrl === '') throw new RuntimeException('Preencha juntos o título e a URL de cada vídeo.');
            if (mb_strlen($videoTitle) > 180) throw new RuntimeException('O título de cada vídeo deve ter no máximo 180 caracteres.');
            if ($this->youtubeEmbedUrl($videoUrl) === '') throw new RuntimeException('Informe uma URL válida de vídeo do YouTube.');
            $videos[] = ['titulo' => $videoTitle, 'url' => mb_substr($videoUrl, 0, 2048)];
        }
        if (!$videos) throw new RuntimeException('Cadastre pelo menos um vídeo de ajuda.');

        $this->ensureSchema();
        $statement = Database::connection()->prepare('INSERT INTO pagina_tutorial_config (id,titulo,texto_introdutorio,videos_json,atualizado_por_conta_id) VALUES (1,:titulo,:texto,:videos,:conta) ON DUPLICATE KEY UPDATE titulo=VALUES(titulo), texto_introdutorio=VALUES(texto_introdutorio), videos_json=VALUES(videos_json), atualizado_por_conta_id=VALUES(atualizado_por_conta_id), updated_at=NOW()');
        $statement->execute([
            ':titulo' => $title,
            ':texto' => $intro ?: null,
            ':videos' => json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':conta' => $accountId,
        ]);
        AuditLogService::record('pagina_tutorial.atualizada', 'pagina_tutorial_config', 1, ['conta_id' => $accountId, 'videos' => count($videos)]);
        return $this->get();
    }

    private function youtubeEmbedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $videoId = '';
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $videoId = explode('/', $path)[0] ?? '';
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if (str_starts_with($path, 'embed/') || str_starts_with($path, 'shorts/')) $videoId = explode('/', $path)[1] ?? '';
            else { parse_str((string) ($parts['query'] ?? ''), $query); $videoId = (string) ($query['v'] ?? ''); }
        }
        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) return '';
        return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId);
    }

    private function defaults(): array
    {
        return ['titulo' => 'Ajuda ao usuário', 'texto_introdutorio' => 'Precisa de ajuda? Assista aos vídeos explicativos abaixo.', 'videos' => [], 'updated_at' => ''];
    }

    private function ensureSchema(): void
    {
        Database::connection()->exec('CREATE TABLE IF NOT EXISTS pagina_tutorial_config (id TINYINT UNSIGNED PRIMARY KEY, titulo VARCHAR(180) NOT NULL, texto_introdutorio TEXT NULL, videos_json LONGTEXT NULL, atualizado_por_conta_id BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_pagina_tutorial_conta (atualizado_por_conta_id)) ENGINE=InnoDB');
    }
}
