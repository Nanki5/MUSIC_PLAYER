<?php

class Upload
{
    public const AUDIO_EXTS = ['mp3', 'flac', 'ogg', 'm4a'];
    public const AUDIO_MIME = [
        'mp3' => 'audio/mpeg',
        'flac' => 'audio/flac',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
    ];
    public const AUDIO_MAX = 50 * 1024 * 1024;

    public const IMAGE_EXTS = ['jpg', 'png', 'webp'];
    public const IMAGE_MIME = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    public const IMAGE_MAX = 5 * 1024 * 1024;

    private const FINFO_ALIASES = [
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'ogg' => ['audio/ogg', 'application/ogg', 'video/ogg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4'],
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public static function guardPostSize(): void
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($type, 'multipart/form-data') !== 0 && stripos($type, 'application/x-www-form-urlencoded') !== 0) {
            return;
        }
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            Http::error('Payload Too Large', 413);
        }
    }

    public static function handle(?array $fileEntry, array $allowedExts, int $maxBytes, array $extToMime): ?array
    {
        if ($fileEntry === null || $fileEntry === [] || !isset($fileEntry['error'])) {
            return null;
        }

        $error = (int)$fileEntry['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            Http::error('File too large', 413);
        }
        if ($error !== UPLOAD_ERR_OK) {
            Http::error('Invalid upload', 422);
        }
        if ((int)$fileEntry['size'] > $maxBytes) {
            Http::error('File too large', 413);
        }

        $name = (string)($fileEntry['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExts, true)) {
            Http::error('Unsupported media type', 415);
        }

        $tmp = (string)$fileEntry['tmp_name'];
        $aliases = self::FINFO_ALIASES[$ext] ?? [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmp);
        if ($detected === false || !in_array($detected, $aliases, true)) {
            Http::error('Unsupported media type', 415);
        }

        if (!isset($extToMime[$ext])) {
            Http::error('Unsupported media type', 415);
        }

        return ['tmp' => $tmp, 'ext' => $ext, 'mime' => $extToMime[$ext]];
    }
}
