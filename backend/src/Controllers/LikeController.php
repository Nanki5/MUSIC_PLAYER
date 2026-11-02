<?php

class LikeController
{
    public static function register(Router $router): void
    {
        $router->post('/api/songs/{id}/like', [self::class, 'toggle']);
        $router->get('/api/me/likes', [self::class, 'index']);
    }

    public static function toggle(array $params): void
    {
        $user = Auth::requireUser();
        $songId = Validate::songIdParam($params['id']);

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM songs WHERE id = ?');
        $stmt->execute([$songId]);
        if ($stmt->fetch() === false) {
            Http::error('Not Found', 404);
        }

        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM likes WHERE user_id = ? AND song_id = ?');
            $delete->execute([$user['id'], $songId]);
            if ($delete->rowCount() > 0) {
                $liked = false;
            } else {
                $insert = $pdo->prepare('INSERT INTO likes (user_id, song_id) VALUES (?, ?)');
                $insert->execute([$user['id'], $songId]);
                $liked = true;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Http::json(['liked' => $liked]);
    }

    public static function index(): void
    {
        $user = Auth::requireUser();
        $pagination = Http::assertValidPagination();

        $stmt = Db::pdo()->prepare(
            'SELECT s.id, s.title, s.author, s.cover_id, s.uploaded_at, l.liked_at, '
            . '(SELECT COUNT(*) FROM likes l2 WHERE l2.song_id = s.id) AS like_count '
            . 'FROM likes l JOIN songs s ON s.id = l.song_id '
            . 'WHERE l.user_id = ? '
            . 'ORDER BY l.liked_at DESC, l.song_id DESC '
            . 'LIMIT ? OFFSET ?'
        );
        $stmt->execute([$user['id'], $pagination['limit'], $pagination['offset']]);

        $likes = [];
        foreach ($stmt->fetchAll() as $row) {
            $likes[] = Serializer::likedSong($row);
        }

        Http::json($likes);
    }
}
