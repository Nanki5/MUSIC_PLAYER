<?php

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }
    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }
    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }
    public function patch(string $pattern, callable $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }
    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);

        if ($method === 'OPTIONS') {
            self::cors();
            http_response_code(204);
            exit;
        }

        self::cors();

        Multipart::parseForPatch();

        if (in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
            Csrf::check();
        }

        $path = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method)
                continue;

            if (preg_match($route['regex'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        Http::error('Not Found', 404);
    }
    private static function cors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, Config::allowList(), true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET,POST,PATCH,DELETE,OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}
