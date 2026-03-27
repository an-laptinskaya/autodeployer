<?php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

require_once ROOT_PATH . 'vendor/autoload.php';

use Autodeployer\Core\App;

$app = new App();
$app->start();
