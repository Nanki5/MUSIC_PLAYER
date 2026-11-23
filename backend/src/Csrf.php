<?php

class Csrf
{
    public static function check(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            self::assertAllowed($origin);
            return;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer === '') {
            Http::error('Forbidden', 403);
        }
        $parts = parse_url($referer);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            Http::error('Forbidden', 403);
        }
        $origin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
        self::assertAllowed($origin);
    }

    private static function assertAllowed(string $origin): void
    {
        if (!in_array($origin, Config::allowList(), true)) {
            Http::error('Forbidden', 403);
        }
    }
}
