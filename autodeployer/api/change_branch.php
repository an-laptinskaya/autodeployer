<?php

$inputJSON = file_get_contents('php://input');

$data = json_decode($inputJSON, true);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/GitClient.php';
require_once __DIR__ . '/../src/DeployRunner.php';
require_once __DIR__ . '/../src/Notifier.php';

$config = require __DIR__ . '/../config/config.php';

try {
    $db = new Autodeployer\Database($config);
    $not = new Autodeployer\Notifier();
    $dep = new Autodeployer\DeployRunner($db, $not);

    // берем ветку, до изменений, чтобы иметь возможность откатиться
    $stmt = $db->getConnection()->prepare("SELECT `target_branch` FROM `{$db->getPrefix()}environments` WHERE id = :id");
    $stmt->execute(['id' => $data['envId']]);
    $oldBranch = $stmt->fetchColumn();

    $db->updateEnvironmentBranch($data['envId'], $data['branchName']);
    $result = $dep->deploy($data['envId'], $data['strategy']);

    if (!$result['success'] && $oldBranch) {
        $db->updateEnvironmentBranch($data['envId'], $oldBranch);
        $result['log'] .= "\n[INFO] База данных откачена к предыдущей ветке: {$oldBranch}";
    }

    echo json_encode($result);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'log' => 'Ошибка: ' . $e->getMessage()]);
}

