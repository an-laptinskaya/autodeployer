<?php

namespace Autodeployer\Controllers;

class UserController extends BaseController
{
    public function index()
    {
        $this->requireAdmin();
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        $stmt = $connection->query("SELECT id, login, is_admin FROM {$prefix}users ORDER BY id ASC");
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('users', [
            'users' => $users,
        ]);
    }

    public function add()
    {
        $this->requireAdmin();
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса']);
            exit;
        }

        try {
            $newLogin = trim($data['login'] ?? '');
            $newPassword = $data['password'] ?? '';
            $isAdmin = !empty($data['is_admin']) ? 1 : 0;

            if (!$newLogin || !$newPassword) {
                echo json_encode(['success' => false, 'error' => 'Заполните логин и пароль.']);
                exit;
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $connection->prepare("INSERT INTO {$prefix}users (login, password_hash, is_admin) VALUES (:login, :hash, :is_admin)");
            $stmt->execute(['login' => $newLogin, 'hash' => $hash, 'is_admin' => $isAdmin]);

            echo json_encode(['success' => true, 'message' => "Пользователь {$newLogin} успешно добавлен."]);
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'error' => "Пользователь с логином '{$data['login']}' уже существует."]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Критическая ошибка: ' . $e->getMessage()]);
        }
    }

    public function delete()
    {
        $this->requireAdmin();
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса']);
            exit;
        }

        try {
            $deleteId = (int)($data['user_id'] ?? 0);

            if ($deleteId === $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'error' => 'Вы не можете удалить собственную учетную запись!']);
                exit;
            }

            $stmt = $connection->prepare("DELETE FROM {$prefix}users WHERE id = :id");
            $stmt->execute(['id' => $deleteId]);

            echo json_encode(['success' => true, 'message' => 'Пользователь успешно удален.']);
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Критическая ошибка: ' . $e->getMessage()]);
        }
    }
}