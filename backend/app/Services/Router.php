<?php

namespace App\Services;

class Router
{
    private $routes = [];
    private $prefix = '';

    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function get(string $path, $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function options(string $path, $handler): self
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    private function addRoute(string $method, string $path, $handler): self
    {
        $fullPath = $this->prefix . $path;
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $this->convertToRegex($fullPath),
            'handler' => $handler
        ];
        return $this;
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        if ($method === 'OPTIONS') {
            $this->sendCorsHeaders();
            return $this->jsonResponse(['message' => 'OK']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];
                
                try {
                    if (is_callable($handler)) {
                        $response = call_user_func($handler, $params);
                    } elseif (is_array($handler)) {
                        [$controller, $action] = $handler;
                        if (is_string($controller)) {
                            $controller = new $controller();
                        }
                        $response = call_user_func([$controller, $action], $params);
                    }
                    return $response;
                } catch (\Exception $e) {
                    return $this->jsonResponse([
                        'error' => $e->getMessage(),
                        'code' => $e->getCode() ?: 500
                    ], 500);
                }
            }
        }

        return $this->jsonResponse(['error' => 'Route not found'], 404);
    }

    public function sendCorsHeaders(): void
    {
        $config = require __DIR__ . '/../../config/config.php';
        $cors = $config['cors'];
        
        header('Access-Control-Allow-Origin: ' . implode(',', $cors['allowed_origins']));
        header('Access-Control-Allow-Methods: ' . implode(',', $cors['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(',', $cors['allowed_headers']));
    }

    public function getInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : array_merge($_GET, $_POST);
    }

    public function jsonResponse($data, int $statusCode = 200)
    {
        $this->sendCorsHeaders();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function success($data = null, string $message = 'success')
    {
        return $this->jsonResponse([
            'code' => 0,
            'message' => $message,
            'data' => $data
        ]);
    }

    public function error(string $message = 'error', int $code = 1, int $statusCode = 400)
    {
        return $this->jsonResponse([
            'code' => $code,
            'message' => $message,
            'data' => null
        ], $statusCode);
    }
}
