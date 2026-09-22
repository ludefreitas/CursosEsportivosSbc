<?php

namespace App\Core;

final class RequestPerformanceMonitor
{
    private static ?string $requestId = null;

    public static function start(float $startedAt, float $slowRequestSeconds): void
    {
        self::$requestId = self::createRequestId();
        $slowRequestSeconds = max(0.1, $slowRequestSeconds);

        if (!headers_sent()) {
            header('X-Request-ID: ' . self::$requestId);
        }

        register_shutdown_function(static function () use ($startedAt, $slowRequestSeconds): void {
            $durationSeconds = max(0.0, microtime(true) - $startedAt);

            if ($durationSeconds < $slowRequestSeconds) {
                return;
            }

            $statusCode = http_response_code();
            if (!is_int($statusCode) || $statusCode < 100) {
                $statusCode = 200;
            }

            $record = [
                'event' => 'requisicao_lenta',
                'request_id' => self::$requestId,
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'path' => self::requestPath(),
                'status' => $statusCode,
                'duration_ms' => (int) round($durationSeconds * 1000),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                'client_disconnected' => connection_aborted() !== 0,
                'occurred_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            ];

            $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                error_log('[Desempenho] ' . $json);
            }
        });
    }

    public static function requestId(): ?string
    {
        return self::$requestId;
    }

    private static function requestPath(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    private static function createRequestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            return str_replace('.', '', uniqid('', true));
        }
    }
}
