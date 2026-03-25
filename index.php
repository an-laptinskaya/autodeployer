<?php

require_once __DIR__ . '/url.php';

require_once ROOT_PATH . 'config/session.php';

$config = require ROOT_PATH . 'config/config.php';
$db = new Autodeployer\Database($config);
$db->initTables();

$page = $_GET['page'] ?? 'branches';

switch ($page) {
    case 'login':
        require ROOT_PATH . 'public/login.php';
        break;
    case 'logout':
        require ROOT_PATH . 'public/logout.php';
        break;
    case 'branches':
        requireLogin();
        require ROOT_PATH . 'public/branches.php';
        break;
    case 'users':
        requireLogin();
        require ROOT_PATH . 'public/users.php';
        break;
    case 'environments':
        requireLogin();
        require ROOT_PATH . 'public/environments.php';
        break;
    case 'webhook':
        requireLogin();
        require ROOT_PATH . 'public/webhook.php';
        break;
    default:
        http_response_code(404);
        echo "Страница не найдена";
        break;
}