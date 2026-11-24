<?php

require __DIR__ . '/src/Config.php';
require __DIR__ . '/src/Db.php';
require __DIR__ . '/src/Minio.php';

function seedDir(): string
{
    return __DIR__ . '/seed';
}

function pngChunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}

function pngBytes(int $width, int $height, int $r, int $g, int $b): string
{
    $row = chr(0) . str_repeat(chr($r) . chr($g) . chr($b), $width);
    $raw = str_repeat($row, $height);
    return "\x89PNG\r\n\x1a\n"
        . pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        . pngChunk('IDAT', gzcompress($raw, 9))
        . pngChunk('IEND', '');
}

function putBytes(string $key, string $bytes, string $contentType): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'seed');
    if ($tmp === false || file_put_contents($tmp, $bytes) === false) {
        throw new RuntimeException('seed: cannot write temp file for ' . $key);
    }
    try {
        Minio::put($key, $tmp, $contentType);
    } finally {
        unlink($tmp);
    }
}

function loadSeedConfig(): array
{
    $path = seedDir() . '/seed.json';
    if (!is_file($path)) {
        throw new RuntimeException('seed: missing ' . $path);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('seed: cannot read ' . $path);
    }
    try {
        $config = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('seed: invalid seed.json: ' . $e->getMessage(), 0, $e);
    }
    foreach (['users', 'songs', 'likes', 'playlists'] as $key) {
        if (!isset($config[$key]) || !is_array($config[$key])) {
            throw new RuntimeException('seed: seed.json is missing the "' . $key . '" array');
        }
    }
    return $config;
}

function requireString(array $data, string $key): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value) || $value === '') {
        throw new RuntimeException('seed: missing or invalid "' . $key . '" in seed.json');
    }
    return $value;
}

function seedFilePath(string $relative): string
{
    if ($relative === '' || str_contains($relative, "\0") || str_starts_with($relative, '/')) {
        throw new RuntimeException('seed: invalid file path "' . $relative . '"');
    }
    if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $relative) === 1) {
        throw new RuntimeException('seed: path escapes the seed folder: "' . $relative . '"');
    }
    $root = realpath(seedDir());
    $path = realpath(seedDir() . '/' . $relative);
    if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('seed: file not found: "' . $relative . '"');
    }
    return $path;
}

function contentTypeFor(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
        'mp3' => 'audio/mpeg',
        'flac' => 'audio/flac',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => throw new RuntimeException('seed: unsupported file extension .' . $ext),
    };
}

function putSeedFile(string $key, string $relative): void
{
    $path = seedFilePath($relative);
    Minio::put($key, $path, contentTypeFor($path));
}

function lookupId(array $map, string $key, string $kind): int
{
    if (!isset($map[$key])) {
        throw new RuntimeException('seed: unknown ' . $kind . ' "' . $key . '" in seed.json');
    }
    return $map[$key];
}

function ensureUser(string $login, string $password): int
{
    $pdo = Db::pdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE login = ?');
    $stmt->execute([$login]);
    $row = $stmt->fetch();
    if ($row) {
        return (int)$row['id'];
    }
    $stmt = $pdo->prepare('INSERT INTO users (login, hashed_password) VALUES (?, ?) RETURNING id');
    $stmt->execute([$login, password_hash($password, PASSWORD_BCRYPT)]);
    return (int)$stmt->fetchColumn();
}

function ensureSong(int $uploaderId, array $song): int
{
    $title = requireString($song, 'title');
    $author = requireString($song, 'author');
    $pdo = Db::pdo();

    $stmt = $pdo->prepare('SELECT id FROM songs WHERE title = ? AND uploader_id = ?');
    $stmt->execute([$title, $uploaderId]);
    $row = $stmt->fetch();
    $existed = $row !== false;
    if ($existed) {
        $id = (int)$row['id'];
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO songs (title, author, cover_id, uploader_id) VALUES (?, ?, 'cover/default', ?) RETURNING id"
        );
        $stmt->execute([$title, $author, $uploaderId]);
        $id = (int)$stmt->fetchColumn();
    }

    try {
        putSeedFile('song/' . $id, requireString($song, 'music'));
        $coverId = 'cover/' . $id;
        putSeedFile($coverId, requireString($song, 'cover'));
    } catch (Throwable $e) {
        if (!$existed) {
            $pdo->prepare('DELETE FROM songs WHERE id = ?')->execute([$id]);
        }
        throw $e;
    }

    $pdo->prepare('UPDATE songs SET author = ?, cover_id = ? WHERE id = ?')
        ->execute([$author, $coverId, $id]);
    return $id;
}

function ensureLike(int $userId, int $songId): void
{
    Db::pdo()->prepare('INSERT INTO likes (user_id, song_id) VALUES (?, ?) ON CONFLICT DO NOTHING')
        ->execute([$userId, $songId]);
}

function ensurePlaylist(array $playlist, int $ownerId, array $songIds): int
{
    $name = requireString($playlist, 'name');
    $published = !empty($playlist['published']);
    $pdo = Db::pdo();

    $stmt = $pdo->prepare('SELECT id FROM playlists WHERE name = ? AND user_id = ?');
    $stmt->execute([$name, $ownerId]);
    $row = $stmt->fetch();
    if ($row) {
        $id = (int)$row['id'];
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO playlists (name, published, picture_id, user_id) VALUES (?, false, 'playlistPicture/default', ?) RETURNING id"
        );
        $stmt->execute([$name, $ownerId]);
        $id = (int)$stmt->fetchColumn();
    }

    $picture = $playlist['picture'] ?? null;
    if (is_string($picture) && $picture !== '') {
        putSeedFile('playlistPicture/' . $id, $picture);
        $pictureId = 'playlistPicture/' . $id;
    } elseif ($picture === null || $picture === '') {
        $pictureId = 'playlistPicture/default';
    } else {
        throw new RuntimeException('seed: invalid "picture" for playlist "' . $name . '"');
    }

    $pdo->prepare(
        'UPDATE playlists SET published = ' . ($published ? 'true' : 'false') . ', picture_id = ? WHERE id = ?'
    )->execute([$pictureId, $id]);
    return $id;
}

function setPlaylistSongs(int $playlistId, array $songIds): void
{
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM playlist_songs WHERE playlist_id = ?')->execute([$playlistId]);
        $stmt = $pdo->prepare('INSERT INTO playlist_songs (playlist_id, song_id, position) VALUES (?, ?, ?)');
        foreach (array_values($songIds) as $position => $songId) {
            $stmt->execute([$playlistId, $songId, $position]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function main(): void
{
    Minio::ensureBucket();

    $defaultPng = pngBytes(1, 1, 255, 255, 255);
    putBytes('cover/default', $defaultPng, 'image/png');
    putBytes('playlistPicture/default', $defaultPng, 'image/png');

    $config = loadSeedConfig();

    $userIds = [];
    foreach ($config['users'] as $user) {
        $login = requireString($user, 'login');
        $userIds[$login] = ensureUser($login, requireString($user, 'password'));
    }

    $songIds = [];
    foreach ($config['songs'] as $song) {
        $title = requireString($song, 'title');
        $uploader = requireString($song, 'uploader');
        $songIds[$title] = ensureSong(lookupId($userIds, $uploader, 'user'), $song);
    }

    foreach ($config['likes'] as $like) {
        $userId = lookupId($userIds, requireString($like, 'user'), 'user');
        $songId = lookupId($songIds, requireString($like, 'song'), 'song');
        ensureLike($userId, $songId);
    }

    $playlistIds = [];
    foreach ($config['playlists'] as $playlist) {
        $name = requireString($playlist, 'name');
        $ownerId = lookupId($userIds, requireString($playlist, 'owner'), 'user');
        $memberIds = [];
        foreach ($playlist['songs'] ?? [] as $title) {
            $memberIds[] = lookupId($songIds, (string)$title, 'song');
        }
        $playlistIds[$name] = ensurePlaylist($playlist, $ownerId, $memberIds);
        setPlaylistSongs($playlistIds[$name], $memberIds);
    }

    echo "Seed complete\n";
    foreach ($userIds as $login => $id) {
        echo "User: {$login}=#{$id}\n";
    }
    echo 'Songs: ' . count($songIds) . " (see seed/seed.json for titles, audio and covers)\n";
    foreach ($playlistIds as $name => $id) {
        echo "Playlist: {$name}=#{$id}\n";
    }
    echo "Default objects: cover/default, playlistPicture/default\n";
    echo 'Audio credit: ' . ($config['attribution']['audio'] ?? 'n/a') . "\n";
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    main();
}
