<?php

namespace App\Services;

use RuntimeException;

class HumanVerificationService
{
    public function createChallenge(): array
    {
        $id = bin2hex(random_bytes(16));
        $_SESSION['human_verification_challenges'] = array_slice(
            (array) ($_SESSION['human_verification_challenges'] ?? []),
            -9,
            null,
            true
        );
        $_SESSION['human_verification_challenges'][$id] = time();

        return ['id' => $id];
    }

    public function validateRequest(array $data): void
    {
        $id = trim((string) ($data['human_verification_id'] ?? ''));
        $accepted = (string) ($data['human_verification'] ?? '') === '1';
        $honeypot = trim((string) ($data['website'] ?? ''));
        $createdAt = (int) (($_SESSION['human_verification_challenges'] ?? [])[$id] ?? 0);

        if ($honeypot !== '' || !$accepted || $id === '' || $createdAt <= 0) {
            throw new RuntimeException('Marque “Não sou robô” para continuar.');
        }

        unset($_SESSION['human_verification_challenges'][$id]);

        if ((time() - $createdAt) < 1) {
            throw new RuntimeException('A verificação foi enviada rápido demais. Marque novamente e tente continuar.');
        }
    }
}
