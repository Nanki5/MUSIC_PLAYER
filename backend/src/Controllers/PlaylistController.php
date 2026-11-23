<?php

class PlaylistController
{
    public static function register(Router $router): void
    {
        $router->post('/api/playlists', [self::class, 'create']);
        $router->get('/api/playlists/{id}', [self::class, 'show']);
        $router->get('/api/me/playlists', [self::class, 'mine']);
        $router->patch('/api/playlists/{id}', [self::class, 'rename']);
        $router->patch('/api/playlists/{id}/picture', [self::class, 'changePicture']);
        $router->delete('/api/playlists/{id}', [self::class, 'destroy']);
        $router->post('/api/playlists/{id}/songs/{songId}', [self::class, 'toggleSong']);
    }

    public static function create(array $params): void
    {
        $user = Auth::requireUser();
        Upload::guardPostSize();

        $name = Validate::requireString($_POST, 'name', 100);
        $songIds = self::parseSongIds();
        self::assertSongsExist($songIds);
        $picture = Upload::handle(
            $_FILES['picture'] ?? null,
            Upload::IMAGE_EXTS,
            Upload::IMAGE_MAX,
            Upload::IMAGE_MIME
        );

        $pdo = Db::pdo();
        $playlistId = 0;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO playlists (name, published, picture_id, user_id)"
                . " VALUES (?, false, 'playlistPicture/default', ?) RETURNING id"
            );
            $stmt->execute([$name, $user['id']]);
            $playlistId = (int)$stmt->fetchColumn();
            if ($songIds !== []) {
                $insert = $pdo->prepare(
                    'INSERT INTO playlist_songs (playlist_id, song_id, position) VALUES (?, ?, ?)'
                );
                foreach ($songIds as $position => $songId) {
                    $insert->execute([$playlistId, $songId, $position]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Http::error('Internal Server Error', 500);
        }

        if ($picture !== null) {
            $key = 'playlistPicture/' . $playlistId;
            try {
                Minio::put($key, $picture['tmp'], $picture['mime']);
            } catch (Throwable $e) {
                $pdo->prepare('DELETE FROM playlists WHERE id = ?')->execute([$playlistId]);
                try {
                    Minio::delete($key);
                } catch (Throwable $ignored) {
                }
                Http::error('Internal Server Error', 500);
            }
        }

        Http::json(self::loadFullPlaylist($playlistId), 201);
    }

    public static function show(array $params): void
    {
        $playlistId = self::playlistIdParam($params['id']);
        $row = self::fetchPlaylist($playlistId);
        if ($row === null) {
            Http::error('Not Found', 404);
        }
        if (!$row['published']) {
            $user = Auth::optionalUser();
            if ($user === null || $user['id'] !== (int)$row['user_id']) {
                Http::error('Not Found', 404);
            }
        }
        Http::json(Serializer::playlist($row, self::loadSongs($playlistId)));
    }

    public static function mine(array $params): void
    {
        $user = Auth::requireUser();
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, published, picture_id FROM playlists WHERE user_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$user['id']]);
        $playlists = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['published'] = self::publishedFlag($row['published']);
            $playlists[] = Serializer::playlistMeta($row);
        }
        Http::json($playlists);
    }

    public static function rename(array $params): void
    {
        $user = Auth::requireUser();
        $playlistId = self::playlistIdParam($params['id']);
        $row = self::fetchPlaylist($playlistId);
        if ($row === null || (int)$row['user_id'] !== $user['id']) {
            Http::error('Not Found', 404);
        }
        $name = Validate::requireString(Http::input(), 'name', 100);
        Db::pdo()->prepare('UPDATE playlists SET name = ? WHERE id = ?')->execute([$name, $playlistId]);
        Http::json(self::loadFullPlaylist($playlistId));
    }

    public static function changePicture(array $params): void
    {
        $user = Auth::requireUser();
        Upload::guardPostSize();
        $playlistId = self::playlistIdParam($params['id']);
        $row = self::fetchPlaylist($playlistId);
        if ($row === null || (int)$row['user_id'] !== $user['id']) {
            Http::error('Not Found', 404);
        }
        $picture = Upload::handle(
            $_FILES['picture'] ?? null,
            Upload::IMAGE_EXTS,
            Upload::IMAGE_MAX,
            Upload::IMAGE_MIME
        );
        if ($picture === null) {
            Http::error('Invalid picture', 422);
        }
        $key = 'playlistPicture/' . $playlistId;
        try {
            Minio::put($key, $picture['tmp'], $picture['mime']);
        } catch (Throwable $e) {
            Http::error('Internal Server Error', 500);
        }
        Db::pdo()->prepare('UPDATE playlists SET picture_id = ? WHERE id = ?')->execute([$key, $playlistId]);
        Http::json(self::loadFullPlaylist($playlistId));
    }

    public static function destroy(array $params): void
    {
        $user = Auth::requireUser();
        $playlistId = self::playlistIdParam($params['id']);
        $row = self::fetchPlaylist($playlistId);
        if ($row === null || (int)$row['user_id'] !== $user['id']) {
            Http::error('Not Found', 404);
        }
        Db::pdo()->prepare('DELETE FROM playlists WHERE id = ?')->execute([$playlistId]);
        if ((string)$row['picture_id'] !== 'playlistPicture/default') {
            try {
                Minio::delete('playlistPicture/' . $playlistId);
            } catch (Throwable $e) {
                Http::error('Internal Server Error', 500);
            }
        }
        Http::noContent();
    }

    public static function toggleSong(array $params): void
    {
        $user = Auth::requireUser();
        $playlistId = self::playlistIdParam($params['id']);
        $songId = Validate::songIdParam($params['songId']);

        $row = self::fetchPlaylist($playlistId);
        if ($row === null || (int)$row['user_id'] !== $user['id']) {
            Http::error('Not Found', 404);
        }
        $song = Db::pdo()->prepare('SELECT id FROM songs WHERE id = ?');
        $song->execute([$songId]);
        if ($song->fetch() === false) {
            Http::error('Not Found', 404);
        }

        $pdo = Db::pdo();
        $added = false;
        try {
            $pdo->beginTransaction();
            $member = $pdo->prepare('SELECT 1 FROM playlist_songs WHERE playlist_id = ? AND song_id = ?');
            $member->execute([$playlistId, $songId]);
            if ($member->fetch() !== false) {
                $delete = $pdo->prepare('DELETE FROM playlist_songs WHERE playlist_id = ? AND song_id = ?');
                $delete->execute([$playlistId, $songId]);
            } else {
                $max = $pdo->prepare(
                    'SELECT COALESCE(MAX(position), -1) + 1 FROM playlist_songs WHERE playlist_id = ?'
                );
                $max->execute([$playlistId]);
                $position = (int)$max->fetchColumn();
                $insert = $pdo->prepare(
                    'INSERT INTO playlist_songs (playlist_id, song_id, position) VALUES (?, ?, ?)'
                );
                $insert->execute([$playlistId, $songId, $position]);
                $added = true;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Http::error('Internal Server Error', 500);
        }
        Http::json(['added' => $added]);
    }

    private static function playlistIdParam(string $raw): int
    {
        if (preg_match('/^\d+$/', $raw) !== 1 || (int)$raw < 1) {
            Http::error('Not Found', 404);
        }
        return (int)$raw;
    }

    private static function parseSongIds(): array
    {
        $raw = $_POST['songs'] ?? null;
        if (!is_string($raw)) {
            Http::error('Invalid songs', 422);
        }
        $trimmed = trim($raw);
        if (!str_starts_with($trimmed, '[')) {
            Http::error('Invalid songs', 422);
        }
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            Http::error('Invalid songs', 422);
        }
        $songIds = [];
        foreach ($decoded as $value) {
            if (!is_int($value) || $value < 1) {
                Http::error('Invalid songs', 422);
            }
            $songIds[] = $value;
        }
        if (count($songIds) !== count(array_unique($songIds))) {
            Http::error('Invalid songs', 422);
        }
        return $songIds;
    }

    private static function assertSongsExist(array $songIds): void
    {
        if ($songIds === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($songIds), '?'));
        $stmt = Db::pdo()->prepare('SELECT id FROM songs WHERE id IN (' . $placeholders . ')');
        $stmt->execute($songIds);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($songIds, $existing) !== []) {
            Http::error('Invalid songs', 422);
        }
    }

    private static function fetchPlaylist(int $playlistId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, published, picture_id, user_id FROM playlists WHERE id = ?'
        );
        $stmt->execute([$playlistId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['published'] = self::publishedFlag($row['published']);
        return $row;
    }

    private static function publishedFlag(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }

    private static function loadSongs(int $playlistId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT s.*, ps.position FROM playlist_songs ps'
            . ' JOIN songs s ON s.id = ps.song_id WHERE ps.playlist_id = ?'
            . ' ORDER BY ps.position ASC'
        );
        $stmt->execute([$playlistId]);
        $songs = [];
        foreach ($stmt->fetchAll() as $row) {
            $songs[] = Serializer::songWithPosition($row);
        }
        return $songs;
    }

    private static function loadFullPlaylist(int $playlistId): array
    {
        $row = self::fetchPlaylist($playlistId);
        if ($row === null) {
            Http::error('Not Found', 404);
        }
        return Serializer::playlist($row, self::loadSongs($playlistId));
    }
}
