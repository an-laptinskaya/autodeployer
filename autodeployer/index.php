<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/src/Database.php';

$config = require __DIR__ . '/config/config.php';

$db = new Autodeployer\Database($config);

$db->initTables();

require_once __DIR__ . '/src/GitClient.php';

$git = new Autodeployer\GitClient($_SERVER['DOCUMENT_ROOT']);

echo '<pre>';
var_dump($_SERVER['DOCUMENT_ROOT']);
var_dump($git->getCurrentGitDir());
var_dump($git->getCurrentBranch());
var_dump($git->getRemoteBranches());