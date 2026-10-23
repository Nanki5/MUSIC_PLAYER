<?php

class Auth
{
    public static function requireUser(): array
    {
        $user = self::optionalUser();
        if ($user === null) {
            Http::error('Unauthorized', 401);
        }
        return $user;
    }

    public static function optionalUser(): ?array
    {
        $cookie = $_COOKIE['auth'] ?? '';
        if (!is_string($cookie) || $cookie === '') {
            return null;
        }
        $userId = self::verify($cookie);
        if ($userId === null) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT id, login FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return ['id' => (int)$row['id'], 'login' => (string)$row['login']];
    }

    public static function issueCookie(int $userId): void
    {
        self::setCookie(self::sign($userId), 2592000);
    }

    public static function clearCookie(): void
    {
        self::setCookie('', 0);
    }

    private static function setCookie(string $value, int $maxAge): void
    {
        $secure = self::isSecureRequest() ? '; Secure' : '';
        header(
            'Set-Cookie: auth=' . $value
            . '; Max-Age=' . $maxAge
            . '; Path=/; HttpOnly; SameSite=None' . $secure,
            false
        );
    }

    private static function isSecureRequest(): bool
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $host = preg_replace('/:\d+$/', '', $host);
        $local = in_array($host, ['localhost', '127.0.0.1'], true);
        return $https || !$local;
    }

    private static function sign(int $userId): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode(['user_id' => $userId]));
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $payload, Config::get('JWT_SECRET'), true)
        );
        return $header . '.' . $payload . '.' . $signature;
    }

    private static function verify(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $expected = self::base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $payload, Config::get('JWT_SECRET'), true)
        );
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($data) || !isset($data['user_id']) || !is_int($data['user_id'])) {
            return null;
        }
        return $data['user_id'];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        return (string)base64_decode(strtr($value, '-_', '+/'));
    }
}
