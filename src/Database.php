<?php

namespace Autodeployer;

class Database
{
    private $pdo;
    private $prefix;

    public function __construct(array $config)
    {
        $this->prefix = $config['db_prefix'];

        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']}";

        try {
            $this->pdo = new \PDO(
                $dsn,
                $config['db_user'],
                $config['db_pass'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        } catch (\PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }

    public function initTables()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->prefix}settings (
                setting_key VARCHAR(255) NOT NULL PRIMARY KEY,
                setting_value TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->prefix}users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                login VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                is_admin TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->prefix}environments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                path VARCHAR(255) NOT NULL,
                target_branch VARCHAR(100) NOT NULL,
                build_command VARCHAR(255),
                build_triggers VARCHAR(255)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->prefix}users");
        if ($stmt->fetchColumn() == 0) {
            $hash = password_hash('autodeployer', PASSWORD_DEFAULT);
            $this->pdo->exec("
                    INSERT INTO {$this->prefix}users (login, password_hash, is_admin) 
                    VALUES ('autodeployer', '$hash', 1)
            ");
        }

    }

    /**
     * Обновляет ветку для площадки
     */
    public function updateEnvironmentBranch(int $id, string $branch): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->prefix}environments 
            SET target_branch = :branch 
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'branch' => $branch
        ]);
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }
}