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
    $stmt = $db->getConnection()->prepare("SELECT * FROM `{$db->getPrefix()}environments` WHERE id = :id");
    $stmt->execute(['id' => $data['envId']]);
    $environment = $stmt->fetch(\PDO::FETCH_ASSOC);
    $oldBranch = $environment['target_branch'];

    $db->updateEnvironmentBranch($data['envId'], $data['branchName']);
    $result = $dep->deploy($data['envId'], $data['strategy']);

    $git = new Autodeployer\GitClient($environment['path']);
    $actualBranch = $git->getCurrentBranch();

    if (!$result['success'] && $oldBranch !== $actualBranch) {
        $db->updateEnvironmentBranch($data['envId'], $actualBranch);
        $result['log'] .= "\n[INFO] База данных откачена к предыдущей ветке: {$actualBranch}";
    }

    echo json_encode($result);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'log' => 'Ошибка: ' . $e->getMessage()]);
}

