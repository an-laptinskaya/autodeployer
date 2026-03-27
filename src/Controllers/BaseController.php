<?php

namespace Autodeployer\Controllers;

use Autodeployer\Core\Database;
use Autodeployer\Core\Session;

abstract class BaseController
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    protected function requireLogin(): void
    {
        if (!Session::isLoggedIn()) {
            header("Location: " . BASE_URL . "?page=login");
            exit;
        }
    }

    protected function requireAdmin()
    {
        $this->requireLogin();
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Доступ запрещен. Только для администраторов.");
        }
    }

    protected function render(string $template, array $data = []): void
    {
        extract($data);
        require ROOT_PATH . "views/{$template}.php";
    }
}