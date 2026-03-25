<?php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . DIRECTORY_SEPARATOR);
}

if (!defined('BASE_URL')) {
    // ========================================================================
    // НАСТРОЙКА ПУТИ ДЛЯ БРАУЗЕРА (BASE_URL)
    // ========================================================================
    // Если используется Nginx/Apache ALIAS, жестко укажите путь в $manualBaseUrl
    // Например: $manualBaseUrl = '/deploy/';
    //
    // Если используется ПОДДОМЕН (deploy.site.com) или обычная папка внутри
    // сайта (site.com/autodeployer/), оставьте строку пустой: '',
    // скрипт определит всё автоматически!
    // ========================================================================

    $manualBaseUrl = '';

    if ($manualBaseUrl) {
        define('BASE_URL', $manualBaseUrl);
    } else {
        $rawDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $docRoot = realpath($rawDocRoot) ?: $rawDocRoot;
        $dir = realpath(__DIR__) ?: __DIR__;

        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $dir = str_replace('\\', '/', $dir);

        $baseUrl = str_replace($docRoot, '', $dir);
        $baseUrl = rtrim($baseUrl, '/') . '/';

        define('BASE_URL', $baseUrl);
    }
}

require_once ROOT_PATH . 'vendor/autoload.php';