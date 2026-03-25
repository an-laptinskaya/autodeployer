<?php

require_once dirname(__DIR__) . '/url.php';
require_once ROOT_PATH . 'config/session.php';

header('Content-Type: application/json');

if (empty($_SESSION['is_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен. Только для администраторов.']);
    exit;
}

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!$data || empty($data['action'])) {
    echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса']);
    exit;
}

$config = require ROOT_PATH . 'config/config.php';

try {
    $db = new Autodeployer\Database($config);

    if ($data['action'] === 'add') {
        $newLogin = trim($data['login'] ?? '');
        $newPassword = $data['password'] ?? '';
        $isAdmin = !empty($data['is_admin']) ? 1 : 0;

        if (!$newLogin || !$newPassword) {
            echo json_encode(['success' => false, 'error' => 'Заполните логин и пароль.']);
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $db->getConnection()->prepare("INSERT INTO {$db->getPrefix()}users (login, password_hash, is_admin) VALUES (:login, :hash, :is_admin)");
        $stmt->execute(['login' => $newLogin, 'hash' => $hash, 'is_admin' => $isAdmin]);

        echo json_encode(['success' => true, 'message' => "Пользователь {$newLogin} успешно добавлен."]);

    } elseif ($data['action'] === 'delete') {
        $deleteId = (int)($data['user_id'] ?? 0);

        if ($deleteId === $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Вы не можете удалить собственную учетную запись!']);
            exit;
        }

        $stmt = $db->getConnection()->prepare("DELETE FROM {$db->getPrefix()}users WHERE id = :id");
        $stmt->execute(['id' => $deleteId]);

        echo json_encode(['success' => true, 'message' => 'Пользователь успешно удален.']);

    } else {
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие.']);
    }

} catch (\PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'error' => "Пользователь с логином '{$data['login']}' уже существует."]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Критическая ошибка: ' . $e->getMessage()]);
}