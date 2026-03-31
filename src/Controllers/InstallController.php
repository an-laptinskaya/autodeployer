<?php

namespace Autodeployer\Controllers;

class InstallController extends BaseController
{
    private static $userFields = [
        'id',
        'login',
        'password_hash',
        'is_admin'
    ];

    public function run()
    {
        if (file_exists(ROOT_PATH . 'config/installed.lock')) {
            die("Приложение уже установлено! Удалите файл config/installed.lock, если хотите переустановить.");
        }

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $connection->exec("
                CREATE TABLE IF NOT EXISTS {$prefix}settings (
                    setting_key VARCHAR(255) NOT NULL PRIMARY KEY,
                    setting_value TEXT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");

            $connection->exec("
                CREATE TABLE IF NOT EXISTS {$prefix}users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    login VARCHAR(100) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    is_admin TINYINT(1) DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");

            $connection->exec("
                CREATE TABLE IF NOT EXISTS {$prefix}environments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    path VARCHAR(255) NOT NULL,
                    target_branch VARCHAR(100) NOT NULL,
                    build_command VARCHAR(255),
                    build_triggers VARCHAR(255)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");

            $stmt = $connection->query("SELECT COUNT(*) FROM {$prefix}users");
            if ($stmt->fetchColumn() == 0) {
                $hash = password_hash('autodeployer', PASSWORD_DEFAULT);
                $this->db->verifyColumns('users', self::$userFields);
                $connection->exec("
                    INSERT INTO {$prefix}users (`login`, `password_hash`, `is_admin`) 
                    VALUES ('autodeployer', '$hash', 1)
                ");
            }

            file_put_contents(ROOT_PATH . 'config/installed.lock', 'Установлено: ' . date('Y-m-d H:i:s'));

            $columns = $this->db->prepareColumns('users', ['id']);
            $stmt = $connection->query("SELECT {$columns} FROM {$prefix}users WHERE login = 'autodeployer'");
            $adminId = $stmt->fetchColumn();

            $_SESSION['user_id']   = $adminId;
            $_SESSION['username']  = 'autodeployer';
            $_SESSION['is_admin']  = true;
            $_SESSION['logged_in'] = true;
            session_regenerate_id(true);

            header("Location: " . BASE_URL . "?page=branches");
            exit;

        } catch (\Exception $e) {
            echo "Ошибка при установке БД: " . $e->getMessage();
        }
    }
}