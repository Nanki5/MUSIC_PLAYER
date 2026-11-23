<?php

class SongController
{
    public static function register(Router $router): void
    {
        $router->get('/api/songs', [self::class, 'index']);
        $router->post('/api/songs', [self::class, 'store']);
    }

    public static function index(array $route): void
    {
        $sort = $_GET['sort'] ?? 'uploaded_at';
        if (!is_string($sort) || ($sort !== 'uploaded_at' && $sort !== 'likes')) {
            Http::error('Invalid sort', 422);
        }

        $query = $_GET['query'] ?? '';
        if (!is_string($query)) {
            Http::error('Invalid query', 422);
        }
        $query = trim($query);

        $page = Http::assertValidPagination();

        $sql = 'SELECT s.id, s.title, s.author, s.cover_id, s.uploaded_at,'
            . ' (SELECT COUNT(*) FROM likes l WHERE l.song_id = s.id) AS like_count'
            . ' FROM songs s';
        $params = [];

        if ($query !== '') {
            $sql .= ' WHERE (s.title ILIKE \'%\'||?||\'%\' OR s.author ILIKE \'%\'||?||\'%\')';
            $params[] = $query;
            $params[] = $query;
        }

        $sortExpr = $sort === 'likes' ? 'like_count' : 's.uploaded_at';
        $sql .= ' ORDER BY ' . $sortExpr . ' DESC, s.id DESC LIMIT ? OFFSET ?';
        $params[] = $page['limit'];
        $params[] = $page['offset'];

        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);

        Http::json(array_map([Serializer::class, 'song'], $stmt->fetchAll()));
    }

    public static function store(array $route): void
    {
        $user = Auth::requireUser();
        Upload::guardPostSize();

        $title = Validate::requireString($_POST, 'title', 100);
        $author = Validate::requireString($_POST, 'author', 100);

        $audio = Upload::handle($_FILES['file'] ?? null, Upload::AUDIO_EXTS, Upload::AUDIO_MAX, Upload::AUDIO_MIME);
        if ($audio === null) {
            Http::error('Audio file required', 422);
        }
        $cover = Upload::handle($_FILES['cover'] ?? null, Upload::IMAGE_EXTS, Upload::IMAGE_MAX, Upload::IMAGE_MIME);

        $stmt = Db::pdo()->prepare(
            'INSERT INTO songs (title, author, cover_id, uploader_id)'
            . ' VALUES (?, ?, \'cover/default\', ?) RETURNING id, uploaded_at'
        );
        $stmt->execute([$title, $author, $user['id']]);
        $row = $stmt->fetch();

        $id = (int)$row['id'];
        $coverId = 'cover/default';
        $storedSong = false;
        $storedCover = false;

        try {
            Minio::put('song/' . $id, $audio['tmp'], $audio['mime']);
            $storedSong = true;
            if ($cover !== null) {
                Minio::put('cover/' . $id, $cover['tmp'], $cover['mime']);
                $storedCover = true;
                $coverId = 'cover/' . $id;
                $update = Db::pdo()->prepare('UPDATE songs SET cover_id = ? WHERE id = ?');
                $update->execute([$coverId, $id]);
            }
        } catch (Throwable) {
            if ($storedSong) {
                try {
                    Minio::delete('song/' . $id);
                } catch (Throwable) {
                }
            }
            if ($storedCover) {
                try {
                    Minio::delete('cover/' . $id);
                } catch (Throwable) {
                }
            }
            try {
                Db::pdo()->prepare('DELETE FROM songs WHERE id = ?')->execute([$id]);
            } catch (Throwable) {
            }
            Http::error('Failed to store media', 500);
        }

        Http::json(Serializer::song([
            'id' => $id,
            'title' => $title,
            'author' => $author,
            'cover_id' => $coverId,
            'uploaded_at' => $row['uploaded_at'],
        ]), 201);
    }
}
