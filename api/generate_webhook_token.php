<?php

require_once dirname(__DIR__) . '/url.php';
require_once ROOT_PATH . 'config/session.php';

header('Content-Type: application/json');

if (empty($_SESSION['is_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен. Только для администраторов.']);
    exit;
}

$config = require ROOT_PATH . 'config/config.php';

try {
    $db = new Autodeployer\Database($config);
    $pdo = $db->getConnection();
    $prefix = $db->getPrefix();

    $newToken = bin2hex(random_bytes(16));

    $stmt = $pdo->query("SELECT COUNT(*) FROM {$prefix}settings WHERE setting_key = 'webhook_token'");

    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE {$prefix}settings SET setting_value = ? WHERE setting_key = 'webhook_token'");
        $updateStmt->execute([$newToken]);
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO {$prefix}settings (setting_key, setting_value) VALUES ('webhook_token', ?)");
        $insertStmt->execute([$newToken]);
    }

    echo json_encode(['success' => true, 'token' => $newToken]);

} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}