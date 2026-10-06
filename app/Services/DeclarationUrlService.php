<?php

namespace App\Services;

final class DeclarationUrlService
{
    public static function absolute(string $path, array $server): string
    {
        $host = trim((string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? ''));
        if (!preg_match('/\A(?:[a-z0-9-]+(?:\.[a-z0-9-]+)*|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z/i', $host)) {
            throw new \RuntimeException('Não foi possível determinar o endereço da declaração.');
        }
        $https = strtolower((string) ($server['HTTPS'] ?? ''));
        $forwarded = strtolower(trim(explode(',', (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        $scheme = ($https !== '' && $https !== 'off' && $https !== '0') || $forwarded === 'https' ? 'https' : 'http';
        return $scheme . '://' . $host . '/' . ltrim($path, '/');
    }
}
