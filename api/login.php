<?php

require_once dirname(__DIR__) . '/url.php';
require_once ROOT_PATH . 'config/session.php';

header('Content-Type: application/json');

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса']);
    exit;
}

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

require_once ROOT_PATH . 'src/Database.php';
$config = require ROOT_PATH . 'config/config.php';

$db = new Autodeployer\Database($config);

$stmt = $db->getConnection()->prepare("SELECT * FROM {$db->getPrefix()}users WHERE login = :login");
$stmt->execute(['login' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password_hash'])) {

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['login'];
    $_SESSION['is_admin'] = (bool) $user['is_admin'];
    $_SESSION['logged_in'] = true;

    session_regenerate_id(true);

    echo json_encode(['success' => true]);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный логин или пароль']);
    exit;
}

