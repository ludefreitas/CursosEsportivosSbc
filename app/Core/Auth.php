<?php

namespace App\Core;

class Auth
{
    public static function id(): ?int
    {
        return isset($_SESSION['account_id']) ? (int) $_SESSION['account_id'] : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function login(int $accountId): void
    {
        $_SESSION['account_id'] = $accountId;
        session_regenerate_id(true);
    }

    public static function logout(): void
    {
        unset($_SESSION['account_id']);
        session_regenerate_id(true);
    }
}

