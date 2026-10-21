<?php

class Validate
{
    public static function login(string $value): string
    {
        if (preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $value) !== 1) {
            Http::error('Invalid login', 422);
        }
        return $value;
    }

    public static function password(string $value): string
    {
        $length = strlen($value);
        if ($length < 8 || $length > 72) {
            Http::error('Invalid password', 422);
        }
        return $value;
    }

    public static function requireString(array $body, string $key, int $maxLen): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value)) {
            Http::error('Invalid ' . $key, 422);
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLen) {
            Http::error('Invalid ' . $key, 422);
        }
        return $value;
    }

    public static function songIdParam(string $raw): int
    {
        if (preg_match('/^\d+$/', $raw) !== 1 || (int)$raw < 1) {
            Http::error('Invalid song id', 422);
        }
        return (int)$raw;
    }
}
