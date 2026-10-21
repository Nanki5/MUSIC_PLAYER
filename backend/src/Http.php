<?php

class Http
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function error(string $message, int $status): never
    {
        self::json(['error' => $message], $status);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function mediaUrl(string $key): string
    {
        return rtrim(Config::get('PUBLIC_BASE_URL'), '/') . '/media/' . ltrim($key, '/');
    }

    public static function assertValidPagination(): array
    {
        $limit = $_GET['limit'] ?? '20';
        $offset = $_GET['offset'] ?? '0';

        $limitOk = is_string($limit) && preg_match('/^\d+$/', $limit) === 1;
        $offsetOk = is_string($offset) && preg_match('/^\d+$/', $offset) === 1;

        if (!$limitOk || (int)$limit < 1 || (int)$limit > 100) {
            self::error('Invalid limit', 422);
        }
        if (!$offsetOk || (int)$offset < 0) {
            self::error('Invalid offset', 422);
        }

        return ['limit' => (int)$limit, 'offset' => (int)$offset];
    }
}
