<?php

require_once dirname(__DIR__) . '/url.php';

$inputJSON = file_get_contents('php://input');

$data = json_decode($inputJSON, true);

if (empty($data['envId'])) {
    echo json_encode(['success' => false, 'error' => 'No envId provided']);
    exit;
}

require_once ROOT_PATH . 'src/Database.php';
require_once ROOT_PATH . 'src/GitClient.php';

$config = require ROOT_PATH . 'config/config.php';

try {
    $db = new Autodeployer\Database($config);

    $stmt = $db->getConnection()->prepare("SELECT `path` FROM `{$db->getPrefix()}environments` WHERE id = :id");
    $stmt->execute(['id' => $data['envId']]);
    $environment = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$environment) {
        echo json_encode(['success' => false, 'error' => 'Environment not found']);
        exit;
    }

    $git = new Autodeployer\GitClient($environment['path']);
    $result = $git->fetchAll();

    if ($result['success']) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['output']]);
    }

} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

