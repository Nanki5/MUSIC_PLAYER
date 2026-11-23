<?php

class AuthController
{
    public static function register(Router $router): void
    {
        $router->post('/api/auth/register', [self::class, 'registerUser']);
        $router->post('/api/auth/login', [self::class, 'loginUser']);
        $router->post('/api/auth/logout', [self::class, 'logout']);
        $router->delete('/api/auth/account', [self::class, 'deleteAccount']);
        $router->get('/api/me', [self::class, 'me']);
    }

    public static function registerUser(array $params): void
    {
        $body = Http::input();
        $login = $body['login'] ?? null;
        $password = $body['password'] ?? null;
        if (!is_string($login)) {
            Http::error('Invalid login', 422);
        }
        if (!is_string($password)) {
            Http::error('Invalid password', 422);
        }
        $login = Validate::login($login);
        $password = Validate::password($password);

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE login = ?');
        $stmt->execute([$login]);
        if ($stmt->fetch() !== false) {
            Http::error('Login already taken', 409);
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO users (login, hashed_password) VALUES (?, ?) RETURNING id');
            $stmt->execute([$login, password_hash($password, PASSWORD_BCRYPT)]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            $sqlState = $e->errorInfo[0] ?? $e->getCode();
            if ((string)$sqlState === '23505') {
                Http::error('Login already taken', 409);
            }
            throw $e;
        }

        Auth::issueCookie((int)$row['id']);
        Http::json(['login' => $login], 201);
    }

    public static function loginUser(array $params): void
    {
        $body = Http::input();
        $login = $body['login'] ?? null;
        $password = $body['password'] ?? null;
        if (!is_string($login) || !is_string($password)) {
            Http::error('Invalid credentials', 401);
        }

        $stmt = Db::pdo()->prepare('SELECT id, login, hashed_password FROM users WHERE login = ?');
        $stmt->execute([$login]);
        $row = $stmt->fetch();

        if ($row === false || !password_verify($password, (string)$row['hashed_password'])) {
            Http::error('Invalid credentials', 401);
        }

        Auth::issueCookie((int)$row['id']);
        Http::json(['login' => (string)$row['login']]);
    }

    public static function logout(array $params): void
    {
        Auth::clearCookie();
        Http::noContent();
    }

    public static function deleteAccount(array $params): void
    {
        $user = Auth::requireUser();
        $pdo = Db::pdo();
        $keys = [];

        $stmt = $pdo->prepare('SELECT id, cover_id FROM songs WHERE uploader_id = ?');
        $stmt->execute([$user['id']]);
        foreach ($stmt->fetchAll() as $song) {
            $keys[] = 'song/' . (int)$song['id'];
            if ((string)$song['cover_id'] !== 'cover/default') {
                $keys[] = 'cover/' . (int)$song['id'];
            }
        }

        $stmt = $pdo->prepare('SELECT id, picture_id FROM playlists WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        foreach ($stmt->fetchAll() as $playlist) {
            if ((string)$playlist['picture_id'] !== 'playlistPicture/default') {
                $keys[] = 'playlistPicture/' . (int)$playlist['id'];
            }
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);

        foreach ($keys as $key) {
            Minio::delete($key);
        }

        Auth::clearCookie();
        Http::noContent();
    }

    public static function me(array $params): void
    {
        $user = Auth::requireUser();
        Http::json(['login' => $user['login']]);
    }
}
