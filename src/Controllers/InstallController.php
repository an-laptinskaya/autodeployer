<?php

namespace Autodeployer\Controllers;

class InstallController extends BaseController
{
    public function run()
    {
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        $stmt = $connection->query("SHOW TABLES LIKE '{$prefix}users'");
        if ($stmt->rowCount() > 0) {
            die("Приложение уже установлено! Удалите таблицы в БД, если хотите переустановить.");
        }

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
                $connection->exec("
                    INSERT INTO {$prefix}users (login, password_hash, is_admin) 
                    VALUES ('autodeployer', '$hash', 1)
                ");
            }

            echo "Установка успешно завершена!";

        } catch (\Exception $e) {
            echo "Ошибка при установке БД: " . $e->getMessage();
        }
    }
}