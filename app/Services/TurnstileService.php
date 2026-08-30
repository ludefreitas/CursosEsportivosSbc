<?php

namespace App\Services;

use RuntimeException;

class TurnstileService
{
    public function siteKey(): string
    {
        return trim((string) app_config('turnstile_site_key', ''));
    }

    public function validateRequest(array $data): void
    {
        $secret = trim((string) app_config('turnstile_secret_key', ''));
        $token = trim((string) ($data['cf-turnstile-response'] ?? ''));

        if ($this->siteKey() === '' || $secret === '') {
            throw new RuntimeException('A verificação de segurança ainda não foi configurada. Tente novamente mais tarde.');
        }

        if ($token === '') {
            throw new RuntimeException('Confirme que você não é um robô para continuar.');
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        $result = is_string($response) ? json_decode($response, true) : null;

        if (!is_array($result) || empty($result['success'])) {
            throw new RuntimeException('Não foi possível confirmar a verificação de segurança. Atualize a verificação e tente novamente.');
        }
    }
}
