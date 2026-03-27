<?php

namespace Autodeployer\Controllers;

use Autodeployer\Core\Session;

class AuthController extends BaseController
{
    public function index()
    {
        if (Session::isLoggedIn()) {
            header("Location: " . BASE_URL . "?page=branches");
            exit;
        }
        $this->render('login', []);
    }

    public function login()
    {
        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса']);
            exit;
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        $stmt = $connection->prepare("SELECT * FROM {$prefix}users WHERE login = :login");
        $stmt->execute(['login' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['login'];
            $_SESSION['is_admin'] = (bool) $user['is_admin'];
            $_SESSION['logged_in'] = true;

            session_regenerate_id(true);

            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Неверный логин или пароль']);
            exit;
        }
    }

    public function logout()
    {
        $_SESSION = [];
        session_destroy();

        setcookie(session_name(), '', time() - 3600, '/');

        header("Location: " . BASE_URL . "?page=login");
        exit;
    }
}