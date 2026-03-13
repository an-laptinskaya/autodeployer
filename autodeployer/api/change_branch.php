<?php

$inputJSON = file_get_contents('php://input');

$data = json_decode($inputJSON, true);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/GitClient.php';
require_once __DIR__ . '/../src/DeployRunner.php';
require_once __DIR__ . '/../src/Notifier.php';

$config = require __DIR__ . '/../config/config.php';

$db = new Autodeployer\Database($config);
$not = new Autodeployer\Notifier();
$dep = new Autodeployer\DeployRunner($db, $not);

$db->updateEnvironmentBranch($data['envId'], $data['branchName']);
$result = $dep->deploy($data['envId'], $data['strategy']);


echo json_encode($result);