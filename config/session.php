<?php

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 1440);
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверка, авторизован ли пользователь
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Принудительная переадресация на логин, если не авторизован
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "?page=login");
        exit;
    }
}
