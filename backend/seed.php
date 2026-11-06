<?php

require __DIR__ . '/src/Config.php';
require __DIR__ . '/src/Db.php';
require __DIR__ . '/src/Minio.php';

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

function silentMp3(): string
{
    $frame = "\xFF\xFB\x90\x00" . str_repeat("\x00", 413);
    return str_repeat($frame, 40);
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

function ensureSong(int $uploaderId, string $title, string $author, string $mp3, string $coverPng): int
{
    $pdo = Db::pdo();
    $stmt = $pdo->prepare('SELECT id FROM songs WHERE title = ? AND uploader_id = ?');
    $stmt->execute([$title, $uploaderId]);
    $row = $stmt->fetch();
    if ($row) {
        $id = (int)$row['id'];
        putBytes('song/' . $id, $mp3, 'audio/mpeg');
        putBytes('cover/' . $id, $coverPng, 'image/png');
        $pdo->prepare('UPDATE songs SET author = ?, cover_id = ? WHERE id = ?')
            ->execute([$author, 'cover/' . $id, $id]);
        return $id;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO songs (title, author, cover_id, uploader_id) VALUES (?, ?, 'cover/default', ?) RETURNING id"
    );
    $stmt->execute([$title, $author, $uploaderId]);
    $id = (int)$stmt->fetchColumn();
    try {
        putBytes('song/' . $id, $mp3, 'audio/mpeg');
        putBytes('cover/' . $id, $coverPng, 'image/png');
    } catch (Throwable $e) {
        $pdo->prepare('DELETE FROM songs WHERE id = ?')->execute([$id]);
        throw $e;
    }
    $pdo->prepare('UPDATE songs SET cover_id = ? WHERE id = ?')->execute(['cover/' . $id, $id]);
    return $id;
}

function ensureLike(int $userId, int $songId): void
{
    Db::pdo()->prepare('INSERT INTO likes (user_id, song_id) VALUES (?, ?) ON CONFLICT DO NOTHING')
        ->execute([$userId, $songId]);
}

function ensurePlaylist(int $userId, string $name, bool $customPicture, string $picturePng): int
{
    $pdo = Db::pdo();
    $stmt = $pdo->prepare('SELECT id FROM playlists WHERE name = ? AND user_id = ?');
    $stmt->execute([$name, $userId]);
    $row = $stmt->fetch();
    if ($row) {
        $id = (int)$row['id'];
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO playlists (name, published, picture_id, user_id) VALUES (?, false, 'playlistPicture/default', ?) RETURNING id"
        );
        $stmt->execute([$name, $userId]);
        $id = (int)$stmt->fetchColumn();
    }
    if ($customPicture) {
        putBytes('playlistPicture/' . $id, $picturePng, 'image/png');
        $pictureId = 'playlistPicture/' . $id;
    } else {
        $pictureId = 'playlistPicture/default';
    }
    $pdo->prepare('UPDATE playlists SET picture_id = ? WHERE id = ?')->execute([$pictureId, $id]);
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

    $aliceId = ensureUser('alice', 'password123');
    $bobId = ensureUser('bob', 'password123');

    $mp3 = silentMp3();
    $coverRed = pngBytes(16, 16, 220, 50, 50);
    $coverBlue = pngBytes(16, 16, 50, 100, 220);
    $coverGreen = pngBytes(16, 16, 50, 180, 80);
    $coverPurple = pngBytes(16, 16, 150, 60, 200);

    $midnightId = ensureSong($aliceId, 'Midnight Drive', 'Neon Waves', $mp3, $coverRed);
    $coffeeId = ensureSong($aliceId, 'Coffee Shop Jazz', 'The Lo-Fi Cats', $mp3, $coverBlue);
    $mountainId = ensureSong($bobId, 'Mountain Sunrise', 'Aurora Fields', $mp3, $coverGreen);
    $pixelId = ensureSong($bobId, 'Pixel Parade', 'Bit Crushers', $mp3, $coverPurple);

    ensureLike($aliceId, $mountainId);
    ensureLike($aliceId, $pixelId);
    ensureLike($bobId, $midnightId);

    $chillPng = pngBytes(16, 16, 90, 160, 220);
    $chillId = ensurePlaylist($aliceId, 'Chill Mix', true, $chillPng);
    setPlaylistSongs($chillId, [$midnightId, $coffeeId]);

    $workoutId = ensurePlaylist($bobId, 'Workout', false, '');
    setPlaylistSongs($workoutId, [$mountainId, $pixelId]);

    Db::pdo()->prepare('UPDATE playlists SET published = (id = ?)')->execute([$chillId]);

    echo "Seed complete\n";
    echo "Users:    alice=#{$aliceId} bob=#{$bobId} (password: password123)\n";
    echo "Songs:    Midnight Drive=#{$midnightId} Coffee Shop Jazz=#{$coffeeId}"
        . " Mountain Sunrise=#{$mountainId} Pixel Parade=#{$pixelId}\n";
    echo "Playlists: Chill Mix=#{$chillId} (published=true, custom picture)"
        . " Workout=#{$workoutId} (published=false, default picture)\n";
    echo "Default objects: cover/default, playlistPicture/default\n";
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    main();
}
