<?php

require_once dirname(__DIR__) . '/url.php';
require_once ROOT_PATH . 'config/session.php';

header('Content-Type: application/json');

if (empty($_SESSION['is_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен.']);
    exit;
}

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!$data || empty($data['action'])) {
    echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
    exit;
}

require_once ROOT_PATH . 'src/Database.php';
$config = require ROOT_PATH . 'config/config.php';

try {
    $db = new Autodeployer\Database($config);
    $prefix = $db->getPrefix();
    $pdo = $db->getConnection();

    $name = trim($data['name'] ?? '');
    $path = trim($data['path'] ?? '');
    $branch = trim($data['target_branch'] ?? '');
    $command = trim($data['build_command'] ?? '');
    $triggers = trim($data['build_triggers'] ?? '');

    if ($data['action'] === 'add') {
        if (!$name || !$path || !$branch) {
            echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля (Название, Путь, Ветка).']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO {$prefix}environments (name, path, target_branch, build_command, build_triggers) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $path, $branch, $command, $triggers]);

        echo json_encode(['success' => true, 'message' => 'Площадка успешно добавлена.']);

    } elseif ($data['action'] === 'edit') {
        $id = (int)($data['id'] ?? 0);
        if (!$id || !$name || !$path || !$branch) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные для редактирования.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE {$prefix}environments SET name=?, path=?, target_branch=?, build_command=?, build_triggers=? WHERE id=?");
        $stmt->execute([$name, $path, $branch, $command, $triggers, $id]);

        echo json_encode(['success' => true, 'message' => 'Площадка успешно обновлена.']);

    } elseif ($data['action'] === 'delete') {
        $id = (int)($data['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM {$prefix}environments WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Площадка удалена.']);

    } else {
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие.']);
    }

} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
}