<?php

class Multipart
{
    public static function parseForPatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($method !== 'PATCH' || stripos($contentType, 'multipart/form-data') !== 0) {
            return;
        }

        $boundary = self::boundary($contentType);
        $raw = file_get_contents('php://input');
        if ($boundary === '' || $raw === '') {
            return;
        }

        $segments = explode('--' . $boundary, $raw);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === "--\r\n" || trim($segment) === '--') {
                continue;
            }
            $segment = ltrim($segment, "\r\n");
            $parts = explode("\r\n\r\n", $segment, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$headers, $body] = $parts;
            $body = rtrim($body, "\r\n");
            if (!preg_match('/content-disposition:[^\r\n]*?name="([^"]*)"/i', $headers, $nameMatch)) {
                continue;
            }
            $name = $nameMatch[1];
            $fileMatch = null;
            preg_match('/content-disposition:.*filename="([^"]*)"/i', $headers, $fileMatch);
            if ($fileMatch === null || $fileMatch[1] === '') {
                $_POST[$name] = $body;
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'upl');
            if ($tmp === false || file_put_contents($tmp, $body) === false) {
                continue;
            }
            $type = 'application/octet-stream';
            if (preg_match('/content-type:\s*([^\r\n]+)/i', $headers, $typeMatch)) {
                $type = trim($typeMatch[1]);
            }
            $_FILES[$name] = [
                'name' => $fileMatch[1],
                'type' => $type,
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($body),
            ];
        }
    }

    private static function boundary(string $contentType): string
    {
        if (!preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $m)) {
            return '';
        }
        return trim($m[1] !== '' ? $m[1] : $m[2]);
    }
}
