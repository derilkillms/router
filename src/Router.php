<?php
/**
 * Author   : Muhammad Deril
 * URI      : https://www.nexjson.com/
 * Github   : @derilkillms
 */


namespace Derilkillms\Helper;

class Router {
    protected $routes = [];
    protected $middlewares = [];
    protected $notFoundHandler;

    // Menambahkan route
    public function add(string $method, string $path, callable $handler) {
        $method = strtoupper($method);
        $path = trim($path, '/');
        $this->routes[$method][$path] = $handler;
    }

    // Menambahkan middleware global (dijalankan sebelum route handler)
    public function addMiddleware(callable $middleware) {
        $this->middlewares[] = $middleware;
    }

    // Set handler untuk 404
    public function setNotFoundHandler(callable $handler) {
        $this->notFoundHandler = $handler;
    }

    // Menjalankan router
    public function run() {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];

        // Hilangkan index.php jika ada
        $requestUri = preg_replace('#/index\.php#', '', $requestUri);
        $requestUri = trim($requestUri, '/');

        $routesForMethod = $this->routes[$requestMethod] ?? [];

        foreach ($routesForMethod as $routePath => $handler) {
            $routeParts = explode('/', $routePath);
            $uriParts = explode('/', $requestUri);

            // Jika jumlah segment URI kurang dari route, skip
            if (count($uriParts) < count($routeParts)) {
                continue;
            }

            $params = [];
            $matched = true;

            foreach ($routeParts as $i => $part) {
                if (preg_match('/^{(\w+)}$/', $part, $matches)) {
                    // Parameter dinamis
                    $paramName = $matches[1];
                    $params[$paramName] = $uriParts[$i] ?? null;
                } else {
                    // Segment harus sama persis
                    if (($uriParts[$i] ?? null) !== $part) {
                        $matched = false;
                        break;
                    }
                }
            }

            if ($matched) {
                // Jalankan middleware
                foreach ($this->middlewares as $middleware) {
                    $result = $middleware();
                    if ($result === false) {
                        // Middleware menghentikan eksekusi route
                        return;
                    }
                }

                // Panggil handler dengan parameter
                call_user_func_array($handler, $params);
                return;
            }
        }

        // Jika tidak ada route cocok, jalankan handler 404 jika ada
        if ($this->notFoundHandler) {
            call_user_func($this->notFoundHandler);
        } else {
            header("HTTP/1.0 404 Not Found");
            echo "404 Not Found";
        }
    }
}