<?php

use Autodeployer\Core\Router;
use Autodeployer\Controllers\InstallController;
use Autodeployer\Controllers\AuthController;
use Autodeployer\Controllers\BranchController;
use Autodeployer\Controllers\EnvironmentController;
use Autodeployer\Controllers\UserController;
use Autodeployer\Controllers\WebhookController;

return function (Router $router) {
    $router->get('install', [InstallController::class, 'run']);

    $router->get('login', [AuthController::class, 'index']);
    $router->get('logout', [AuthController::class, 'logout']);
    $router->post('login', [AuthController::class, 'login']);

    $router->get('branches', [BranchController::class, 'index']);
    $router->post('fetch_branches', [BranchController::class, 'fetch']);
    $router->post('change_branch', [BranchController::class, 'deploy']);

    $router->get('environments', [EnvironmentController::class, 'index']);
    $router->post('add_environment', [EnvironmentController::class, 'add']);
    $router->post('edit_environment', [EnvironmentController::class, 'edit']);
    $router->post('delete_environment', [EnvironmentController::class, 'delete']);

    $router->get('users', [UserController::class, 'index']);
    $router->post('add_user', [UserController::class, 'add']);
    $router->post('delete_user', [UserController::class, 'delete']);

    $router->get('webhook', [WebhookController::class, 'index']);
    $router->post('generate_webhook_token', [WebhookController::class, 'generateToken']);
    $router->post('webhook', [WebhookController::class, 'handleIncomingWebhook']);
};