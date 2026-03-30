<?php

namespace Autodeployer\Core;

class Router
{
    private Database $db;
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get(string $path, array $controller)
    {
        $this->routes['GET'][$path] = $controller;
    }

    public function post(string $path, array $controller)
    {
        $this->routes['POST'][$path] = $controller;
    }

    public function dispatch()
    {
        $page = $_GET['page'] ?? 'branches';
        $apiAction = $_GET['api'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'];

        $currentRoute = $apiAction ?: $page;

        if (isset($this->routes[$method][$currentRoute])) {
            $callback = $this->routes[$method][$currentRoute];

            $controllerClass = $callback[0];
            $controllerMethod = $callback[1];

            $controller = new $controllerClass($this->db);
            $controller->$controllerMethod();
        } else {
            http_response_code(404);
            echo "404 Not Found. Маршрут '{$currentRoute}' не найден.";
        }
    }

}