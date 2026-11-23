<?php

class Config
{
    private const DEFAULTS = [
        'JWT_SECRET' => 'devsecret',
        'ALLOWED_ORIGINS' => 'http://localhost:5500,http://localhost:8000',
        'PUBLIC_BASE_URL' => 'http://localhost:8000',
        'DB_HOST' => 'db',
        'DB_PORT' => '5432',
        'DB_NAME' => 'music',
        'DB_USER' => 'music',
        'DB_PASSWORD' => 'music',
        'MINIO_ENDPOINT' => 'http://minio:9000',
        'MINIO_ACCESS_KEY' => 'minioadmin',
        'MINIO_SECRET_KEY' => 'minioadmin',
        'MINIO_BUCKET' => 'music',
    ];

    public static function get(string $key): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (array_key_exists($key, self::DEFAULTS)) {
            return self::DEFAULTS[$key];
        }
        throw new RuntimeException('Missing required environment variable: ' . $key);
    }

    public static function allowList(): array
    {
        $origins = array_map('trim', explode(',', self::get('ALLOWED_ORIGINS')));
        return array_values(array_filter($origins, static fn($o) => $o !== ''));
    }
}
