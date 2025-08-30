<?php

/**
 * Author   : Muhammad Deril
 * URI      : https://www.nexjson.com/
 * Github   : @derilkillms
 */

namespace Derilkillms\Helper;

class Router
{
    protected $routes = [];
    protected $middlewares = [];
    protected $notFoundHandler;

    /**
     * Normalisasi path agar konsisten
     */
    protected function normalizePath(string $path): string
    {
        $path = preg_replace('#/+#', '/', $path);   // ganti banyak slash jadi satu
        $path = '/' . trim($path, '/');             // pastikan ada leading slash
        return $path === '/' ? '/' : rtrim($path, '/'); // hilangkan trailing slash kecuali root
    }

    /**
     * Daftarkan route
     */
    public function add(string $method, string $path, callable $handler)
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Shortcut HTTP methods
     */
    public function get(string $path, callable $handler)
    {
        $this->add('GET', $path, $handler);
    }
    public function post(string $path, callable $handler)
    {
        $this->add('POST', $path, $handler);
    }
    public function put(string $path, callable $handler)
    {
        $this->add('PUT', $path, $handler);
    }
    public function delete(string $path, callable $handler)
    {
        $this->add('DELETE', $path, $handler);
    }
    public function patch(string $path, callable $handler)
    {
        $this->add('PATCH', $path, $handler);
    }
    public function options(string $path, callable $handler)
    {
        $this->add('OPTIONS', $path, $handler);
    }
     public function post_get(string $path, callable $handler)
    {
        $methods = ['GET', 'POST'];
        foreach ($methods as $method) {
            $this->add($method, $path, $handler);
        }
    }
    public function any(string $path, callable $handler)
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->add($method, $path, $handler);
        }
    }
    /**
     * Middleware global
     */
    public function addMiddleware(callable $middleware)
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Handler 404
     */
    public function setNotFoundHandler(callable $handler)
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Ambil URI request dengan base path dibersihkan
     */
    protected function getRequestUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // hilangkan index.php
        $uri = preg_replace('#/index\.php#', '', $uri);

        // hapus base folder (jika aplikasi ada di subfolder)
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }

        return $this->normalizePath($uri);
    }

    /**
     * Jalankan router
     */
    public function run()
    {
        $requestUri    = $this->getRequestUri();
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $routesForMethod = $this->routes[$requestMethod] ?? [];

        foreach ($routesForMethod as $routePath => $handler) {
            $routeParts = explode('/', trim($routePath, '/'));
            $uriParts   = explode('/', trim($requestUri, '/'));

            // Khusus root
            if ($routePath === '/' && $requestUri === '/') {
                return $this->executeHandler($handler, []);
            }

            if (count($uriParts) !== count($routeParts)) {
                continue;
            }

            $params  = [];
            $matched = true;

            foreach ($routeParts as $i => $part) {
                if (preg_match('/^{(\w+)}$/', $part, $matches)) {
                    $params[$matches[1]] = $uriParts[$i] ?? null;
                } elseif (($uriParts[$i] ?? null) !== $part) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return $this->executeHandler($handler, $params);
            }
        }

        // 404 jika tidak ada match
        if ($this->notFoundHandler) {
            call_user_func($this->notFoundHandler);
        } else {
            header("HTTP/1.0 404 Not Found");
            echo "404 Not Found";
        }
    }

    /**
     * Jalankan middleware + handler
     */
    protected function executeHandler(callable $handler, array $params)
    {
        foreach ($this->middlewares as $middleware) {
            $result = $middleware();
            if ($result === false) {
                return;
            }
        }
        call_user_func_array($handler, $params);
    }
}
