<?php

class Minio
{
    private const REGION = 'us-east-1';
    private const SERVICE = 's3';
    private const EMPTY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public static function put(string $key, string $tmpPath, string $contentType): void
    {
        $body = file_get_contents($tmpPath);
        if ($body === false) {
            throw new RuntimeException('MinIO: cannot read temp file ' . $tmpPath);
        }
        $response = self::request('PUT', self::objectUrl($key), $body, ['Content-Type' => $contentType]);
        if ($response['status'] !== 200) {
            throw new RuntimeException('MinIO PUT failed: ' . $response['status'] . ' ' . $response['body']);
        }
    }

    public static function delete(string $key): void
    {
        $response = self::request('DELETE', self::objectUrl($key), '', []);
        if ($response['status'] !== 200 && $response['status'] !== 204 && $response['status'] !== 404) {
            throw new RuntimeException('MinIO DELETE failed: ' . $response['status'] . ' ' . $response['body']);
        }
    }

    public static function ensureBucket(): void
    {
        $endpoint = rtrim(Config::get('MINIO_ENDPOINT'), '/');
        $bucket = Config::get('MINIO_BUCKET');

        $response = self::request('PUT', $endpoint . '/' . $bucket, '', []);
        if ($response['status'] !== 200 && $response['status'] !== 409) {
            throw new RuntimeException('MinIO create bucket failed: ' . $response['status'] . ' ' . $response['body']);
        }

        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => ['arn:aws:s3:::' . $bucket . '/*'],
            ]],
        ]);
        $response = self::request('PUT', $endpoint . '/' . $bucket . '?policy=', $policy, [
            'Content-Type' => 'application/json',
        ]);
        if ($response['status'] !== 200 && $response['status'] !== 204) {
            throw new RuntimeException('MinIO put policy failed: ' . $response['status'] . ' ' . $response['body']);
        }
    }

    private static function objectUrl(string $key): string
    {
        $bucket = rawurlencode(Config::get('MINIO_BUCKET'));
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', trim($key, '/'))));
        return rtrim(Config::get('MINIO_ENDPOINT'), '/') . '/' . $bucket . '/' . $encodedKey;
    }

    private static function request(string $method, string $url, string $body, array $extraHeaders): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new RuntimeException('MinIO: invalid endpoint URL ' . $url);
        }

        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $payloadHash = hash('sha256', $body);
        $amzDate = gmdate('Ymd\THis\Z');

        $headers = [];
        foreach ($extraHeaders as $name => $value) {
            $headers[strtolower($name)] = trim($value);
        }
        $headers['host'] = $host;
        $headers['x-amz-content-sha256'] = $payloadHash;
        $headers['x-amz-date'] = $amzDate;
        ksort($headers);

        $canonicalHeaders = '';
        $signedHeaderNames = [];
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
            $signedHeaderNames[] = $name;
        }
        $signedHeaders = implode(';', $signedHeaderNames);

        $canonicalRequest = implode("\n", [
            $method,
            $parts['path'],
            self::canonicalQuery($parts['query'] ?? ''),
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $dateStamp = substr($amzDate, 0, 8);
        $scope = $dateStamp . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $keyDate = hash_hmac('sha256', $dateStamp, 'AWS4' . Config::get('MINIO_SECRET_KEY'), true);
        $keyRegion = hash_hmac('sha256', self::REGION, $keyDate, true);
        $keyService = hash_hmac('sha256', self::SERVICE, $keyRegion, true);
        $keySigning = hash_hmac('sha256', 'aws4_request', $keyService, true);
        $signature = hash_hmac('sha256', $stringToSign, $keySigning);

        $authorization = 'AWS4-HMAC-SHA256 Credential=' . Config::get('MINIO_ACCESS_KEY') . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $curlHeaders = ['Authorization: ' . $authorization];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('MinIO: curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => $curlHeaders,
        ]);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('MinIO cURL error: ' . $error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string)$responseBody];
    }

    private static function canonicalQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }
        $params = [];
        foreach (explode('&', $query) as $pair) {
            $parts = explode('=', $pair, 2);
            $name = urldecode($parts[0]);
            $value = isset($parts[1]) ? urldecode($parts[1]) : '';
            $params[$name] = $value;
        }
        ksort($params);
        $encoded = [];
        foreach ($params as $name => $value) {
            $encoded[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        return implode('&', $encoded);
    }
}
