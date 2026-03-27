<?php

namespace Autodeployer\Core;

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

    public function getConnection()
    {
        return $this->pdo;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }
}