<?php

require_once dirname(__DIR__) . '/url.php';

require_once ROOT_PATH . 'config/session.php';

$inputJSON = file_get_contents('php://input');

$data = json_decode($inputJSON, true);

$config = require ROOT_PATH . 'config/config.php';

try {
    $db = new Autodeployer\Database($config);
    $dep = new Autodeployer\DeployRunner($db);

    $stmt = $db->getConnection()->prepare("SELECT * FROM `{$db->getPrefix()}environments` WHERE id = :id");
    $stmt->execute(['id' => $data['envId']]);
    $environment = $stmt->fetch(\PDO::FETCH_ASSOC);
    $oldBranch = $environment['target_branch'];

    $db->updateEnvironmentBranch($data['envId'], $data['branchName']);
    $result = $dep->deploy($data['envId'], 'handle', $data['strategy']);

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

