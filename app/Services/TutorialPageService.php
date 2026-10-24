<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class TutorialPageService
{
    public const MAX_VIDEOS = 30;
    public const MAX_VIDEO_BYTES = 40 * 1024 * 1024;
    private const UPLOAD_PREFIX = '/uploads/tutorial-videos/';

    public static function uploadLimitBytes(): int
    {
        $limit = self::iniBytes((string) ini_get('upload_max_filesize'));
        return $limit > 0 ? min(self::MAX_VIDEO_BYTES, $limit) : self::MAX_VIDEO_BYTES;
    }

    public static function requestLimitBytes(): int
    {
        return self::iniBytes((string) ini_get('post_max_size'));
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        return (int) ((float) $value * match ($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 });
    }

    public function get(): array
    {
        $row = Database::connection()->query('SELECT * FROM pagina_tutorial_config WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $this->defaults();
        $videos = json_decode((string) ($row['videos_json'] ?? '[]'), true) ?: [];
        $row['videos'] = array_values(array_map(function (array $video): array {
            $video['tipo'] = ($video['tipo'] ?? 'youtube') === 'local' ? 'local' : 'youtube';
            $video['embed_url'] = $video['tipo'] === 'youtube' ? $this->youtubeEmbedUrl((string) ($video['url'] ?? '')) : '';
            if ($video['tipo'] === 'local' && !$this->isLocalPath((string) ($video['arquivo'] ?? ''))) $video['arquivo'] = '';
            return $video;
        }, $videos));
        return $row;
    }

    public function save(int $accountId, array $data, array $files = []): array
    {
        $title = trim((string) ($data['titulo'] ?? ''));
        $intro = trim((string) ($data['texto_introdutorio'] ?? ''));
        if ($title === '') throw new RuntimeException('Informe o título principal da página de ajuda.');
        if (mb_strlen($title) > 180) throw new RuntimeException('O título principal deve ter no máximo 180 caracteres.');
        if (mb_strlen($intro) > 1500) throw new RuntimeException('O texto introdutório deve ter no máximo 1.500 caracteres.');

        $videoTitles = (array) ($data['video_titulo'] ?? []);
        $videoUrls = (array) ($data['video_url'] ?? []);
        $videoTypes = (array) ($data['video_tipo'] ?? []);
        $currentFiles = (array) ($data['video_arquivo_atual'] ?? []);
        if (count($videoTitles) > self::MAX_VIDEOS || !array_is_list($videoTitles)) throw new RuntimeException('É possível cadastrar no máximo 30 vídeos de ajuda.');
        $previous = $this->get();
        $knownFiles = [];
        foreach ($previous['videos'] as $video) {
            if (($video['tipo'] ?? '') === 'local' && $this->isLocalPath((string) ($video['arquivo'] ?? ''))) $knownFiles[$video['arquivo']] = $video;
        }
        $videos = [];
        $createdFiles = [];
        $persisted = false;
        try {
            foreach (array_keys($videoTitles) as $index) {
                $videoTitle = trim((string) ($videoTitles[$index] ?? ''));
                $videoUrl = trim((string) ($videoUrls[$index] ?? ''));
                $type = (string) ($videoTypes[$index] ?? 'youtube');
                if (!in_array($type, ['youtube', 'local'], true)) throw new RuntimeException('Selecione YouTube ou Arquivo local para cada vídeo.');
                $existing = trim((string) ($currentFiles[$index] ?? ''));
                $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                $hasFile = $type === 'local' && $error !== UPLOAD_ERR_NO_FILE;
                if ($videoTitle === '' && ($type === 'youtube' ? $videoUrl === '' : !$hasFile && $existing === '')) continue;
                if ($videoTitle === '') throw new RuntimeException('Preencha o título de cada vídeo.');
                if (mb_strlen($videoTitle) > 180) throw new RuntimeException('O título de cada vídeo deve ter no máximo 180 caracteres.');
                if ($type === 'youtube') {
                    if (mb_strlen($videoUrl) > 2048 || $this->youtubeEmbedUrl($videoUrl) === '') throw new RuntimeException('Informe uma URL válida de vídeo do YouTube.');
                    $videos[] = ['titulo' => $videoTitle, 'tipo' => 'youtube', 'url' => $videoUrl];
                    continue;
                }
                if ($hasFile) {
                    $file = [];
                    foreach (['name', 'tmp_name', 'size', 'error'] as $key) $file[$key] = $files[$key][$index] ?? null;
                    $stored = $this->storeVideo($file);
                    $createdFiles[] = $stored['arquivo'];
                } else {
                    if (!isset($knownFiles[$existing]) || !is_file(ROOT_PATH . '/public' . $existing)) throw new RuntimeException('Selecione um arquivo MP4 ou WebM para o vídeo local.');
                    $stored = $knownFiles[$existing];
                }
                $videos[] = ['titulo' => $videoTitle, 'tipo' => 'local', 'arquivo' => $stored['arquivo'], 'nome_arquivo' => $stored['nome_arquivo'] ?? basename($stored['arquivo'])];
            }
            $statement = Database::connection()->prepare('INSERT INTO pagina_tutorial_config (id,titulo,texto_introdutorio,videos_json,atualizado_por_conta_id) VALUES (1,:titulo,:texto,:videos,:conta) ON DUPLICATE KEY UPDATE titulo=VALUES(titulo), texto_introdutorio=VALUES(texto_introdutorio), videos_json=VALUES(videos_json), atualizado_por_conta_id=VALUES(atualizado_por_conta_id), updated_at=NOW()');
            $statement->execute([
                ':titulo' => $title,
                ':texto' => $intro ?: null,
                ':videos' => json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':conta' => $accountId,
            ]);
            $persisted = true;
            $retained = array_column($videos, 'arquivo');
            foreach (array_keys($knownFiles) as $oldPath) {
                if (!in_array($oldPath, $retained, true)) $this->deleteLocalFile($oldPath);
            }
            AuditLogService::record('pagina_tutorial.atualizada', 'pagina_tutorial_config', 1, ['conta_id' => $accountId, 'videos' => count($videos)]);
            return $this->get();
        } catch (\Throwable $error) {
            if (!$persisted) foreach ($createdFiles as $path) $this->deleteLocalFile($path);
            throw $error;
        }
    }

    private function isLocalPath(string $path): bool
    {
        return preg_match('~^/uploads/tutorial-videos/[a-f0-9]{32}\.(mp4|webm)$~D', $path) === 1;
    }

    private function deleteLocalFile(string $path): void
    {
        if ($this->isLocalPath($path) && is_file(ROOT_PATH . '/public' . $path)) @unlink(ROOT_PATH . '/public' . $path);
    }

    private function storeVideo(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new RuntimeException('O vídeo ultrapassa o limite de tamanho permitido pelo servidor.');
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível receber o vídeo. Selecione o arquivo novamente e tente salvar.');
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Arquivo de vídeo inválido.');
        if (filesize($tmp) > self::uploadLimitBytes()) throw new RuntimeException('O vídeo ultrapassa o limite de tamanho permitido.');
        $mime = (string) mime_content_type($tmp);
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['mp4' => ['video/mp4', 'application/mp4'], 'webm' => ['video/webm']];
        if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) throw new RuntimeException('Envie um vídeo válido em MP4 ou WebM.');
        $directory = ROOT_PATH . '/public' . self::UPLOAD_PREFIX;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Não foi possível preparar a pasta dos vídeos.');
        $path = self::UPLOAD_PREFIX . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($tmp, ROOT_PATH . '/public' . $path)) throw new RuntimeException('Não foi possível salvar o vídeo.');
        return ['arquivo' => $path, 'nome_arquivo' => mb_substr(basename(str_replace('\\', '/', (string) $file['name'])), 0, 255)];
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

}
