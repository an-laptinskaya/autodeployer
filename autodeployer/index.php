<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/public/branches.php';

//require_once __DIR__ . '/src/Database.php';
//
//$config = require __DIR__ . '/config/config.php';
//
//$db = new Autodeployer\Database($config);
//
//$db->initTables();
//
//require_once __DIR__ . '/src/GitClient.php';
//require_once __DIR__ . '/src/DeployRunner.php';
//require_once __DIR__ . '/src/Notifier.php';
//
//$git = new Autodeployer\GitClient($_SERVER['DOCUMENT_ROOT']);
//$not = new Autodeployer\Notifier();
//$dep = new Autodeployer\DeployRunner($db, $not);
//
//echo '<pre>';
//var_dump($git->getCurrentGitDir());
//var_dump($git->getCurrentBranch());
//var_dump($git->getRemoteBranches());
//$userConfig = $git->setGitUserConfig();
//var_dump($userConfig);
//
//var_dump($db->updateEnvironmentBranch(1, 'test'));
//var_dump($dep->deploy(1));

//$git->checkout('master');

