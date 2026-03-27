<?php

namespace Autodeployer\Core;

class App
{
    private Router $router;
    private Database $db;
    private array $config;

    public function __construct()
    {
        Session::start();
        $this->config = require ROOT_PATH . 'config/config.php';
        $this->defineBaseUrl();
        $this->db = new Database($this->config);
        $this->router = new Router($this->db);
    }

    public function start()
    {
        $this->loadRoutes();
        $this->router->dispatch();
    }

    private function loadRoutes(): void
    {
        $registerRoutes = require ROOT_PATH . 'routes/routes.php';
        $registerRoutes($this->router);
    }

    private function defineBaseUrl()
    {
        if (defined('BASE_URL')) {
            return;
        }

        if (!empty($this->config['base_url'])) {
            define('BASE_URL', $this->config['base_url']);
            return;
        }

        $rawDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $docRoot = realpath($rawDocRoot) ?: $rawDocRoot;
        $dir = realpath(ROOT_PATH) ?: ROOT_PATH;

        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $dir = str_replace('\\', '/', $dir);

        $baseUrl = str_replace($docRoot, '', $dir);
        $baseUrl = rtrim($baseUrl, '/') . '/';

        define('BASE_URL', $baseUrl);
    }
}