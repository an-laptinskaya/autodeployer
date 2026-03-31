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

    public function verifyColumns(string $table, array $columns)
    {
        if ($columns === ['*'] || empty($columns)) {
            return '*';
        }

        $tableWithPrefix = $this->prefix . $table;

        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$tableWithPrefix}`");
        $tableColumns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($columns as $col) {
            if (!in_array($col, $tableColumns, true)) {
                throw new \Exception("Колонка {$col} не найдена в таблице {$tableWithPrefix}");
            }
        }
    }

    public function formatColumns(array $columns): string
    {
        if ($columns === ['*'] || empty($columns)) {
            return '*';
        }

        $formatted = [];
        foreach ($columns as $col) {
            $formatted[] = "`{$col}`";
        }

        return implode(', ', $formatted);
    }

    public function prepareColumns(string $table, array $columns)
    {
        $this->verifyColumns($table, $columns);
        return $this->formatColumns($columns);
    }

}