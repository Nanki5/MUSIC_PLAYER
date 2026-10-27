<?php

$base = dirname(__DIR__);

require $base . '/src/Config.php';
require $base . '/src/Http.php';
require $base . '/src/Db.php';
require $base . '/src/Auth.php';
require $base . '/src/Csrf.php';
require $base . '/src/Validate.php';
require $base . '/src/Upload.php';
require $base . '/src/Minio.php';
require $base . '/src/Serializer.php';
require $base . '/src/Router.php';

$router = new Router();

$controllers = ['AuthController', 'SongController', 'LikeController', 'PlaylistController'];
foreach ($controllers as $controller) {
    $file = $base . '/src/Controllers/' . $controller . '.php';
    if (file_exists($file)) {
        require $file;
        $controller::register($router);
    }
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
