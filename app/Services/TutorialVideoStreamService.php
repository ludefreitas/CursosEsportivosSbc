<?php

namespace App\Services;

/** Entrega apenas vídeos públicos de ajuda, antes de iniciar sessão ou acessar o banco. */
class TutorialVideoStreamService
{
    public static function serve(string $root, string $requestPath): bool
    {
        if (!preg_match('~^/uploads/tutorial-videos/[a-f0-9]{32}\.(mp4|webm)$~D', $requestPath)) return false;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            header('Content-Length: 0');
            return true;
        }

        $directory = realpath($root . '/public/uploads/tutorial-videos');
        $file = realpath($root . '/public' . $requestPath);
        if ($directory === false || $file === false || !str_starts_with($file, $directory . DIRECTORY_SEPARATOR) || !is_file($file)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            if ($method !== 'HEAD') echo 'Vídeo não encontrado.';
            return true;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            if ($method !== 'HEAD') echo 'Vídeo não disponível.';
            return true;
        }
        $stat = fstat($handle);
        $size = (int) $stat['size'];
        $etag = '"' . basename($file) . '-' . $size . '-' . $stat['mtime'] . '"';
        $modified = gmdate('D, d M Y H:i:s', (int) $stat['mtime']) . ' GMT';
        header('Content-Type: ' . (str_ends_with($file, '.webm') ? 'video/webm' : 'video/mp4'));
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=86400');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $modified);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        // Os intervalos e o tamanho referem-se aos bytes originais do arquivo.
        @ini_set('zlib.output_compression', '0');

        $start = 0;
        $end = $size - 1;
        $range = $method === 'GET' ? trim((string) ($_SERVER['HTTP_RANGE'] ?? '')) : '';
        $ifRange = trim((string) ($_SERVER['HTTP_IF_RANGE'] ?? ''));
        if ($ifRange !== '' && $ifRange !== $etag && $ifRange !== $modified) $range = '';
        if ($range !== '') {
            $valid = preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $match) === 1;
            if ($valid && ($match[1] !== '' || $match[2] !== '') && $size > 0) {
                if ($match[1] === '') {
                    $suffix = (int) $match[2];
                    $start = max(0, $size - $suffix);
                    $valid = $suffix > 0;
                } else {
                    $start = (int) $match[1];
                    $end = $match[2] === '' ? $end : min($end, (int) $match[2]);
                }
                $valid = $valid && $start < $size && $start <= $end;
            } else {
                $valid = false;
            }
            if (!$valid) {
                fclose($handle);
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                header('Content-Length: 0');
                return true;
            }
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }

        $length = max(0, $end - $start + 1);
        header('Content-Length: ' . $length);
        if ($method === 'HEAD' || $length === 0) {
            fclose($handle);
            return true;
        }
        while (ob_get_level() > 0) ob_end_clean();
        fseek($handle, $start);
        while ($length > 0 && !feof($handle) && !connection_aborted()) {
            $chunk = fread($handle, min(65536, $length));
            if ($chunk === false || $chunk === '') break;
            echo $chunk;
            $length -= strlen($chunk);
        }
        fclose($handle);
        return true;
    }
}
