<?php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . DIRECTORY_SEPARATOR);
}

if (!defined('BASE_URL')) {
    $rawDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $docRoot = realpath($rawDocRoot) ?: $rawDocRoot;
    $dir = realpath(__DIR__) ?: __DIR__;

    $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
    $dir = str_replace('\\', '/', $dir);

    $baseUrl = str_replace($docRoot, '', $dir);
    $baseUrl = rtrim($baseUrl, '/') . '/';

    define('BASE_URL', $baseUrl);
}