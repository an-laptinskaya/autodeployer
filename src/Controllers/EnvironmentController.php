<?php

namespace Autodeployer\Controllers;

class EnvironmentController extends BaseController
{
    public function index()
    {
        $this->requireAdmin();
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        $stmt = $connection->query("SELECT * FROM {$prefix}environments ORDER BY id ASC");
        $environments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('environments', [
            'environments' => $environments,
        ]);
    }

    public function add()
    {
        $this->requireAdmin();
        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
            exit;
        }

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $name = trim($data['name'] ?? '');
            $path = trim($data['path'] ?? '');
            $branch = trim($data['target_branch'] ?? '');
            $command = trim($data['build_command'] ?? '');
            $triggers = trim($data['build_triggers'] ?? '');

            if (!$name || !$path || !$branch) {
                echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля (Название, Путь, Ветка).']);
                exit;
            }

            $stmt = $connection->prepare("INSERT INTO {$prefix}environments (name, path, target_branch, build_command, build_triggers) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $path, $branch, $command, $triggers]);

            echo json_encode(['success' => true, 'message' => 'Площадка успешно добавлена.']);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function edit()
    {
        $this->requireAdmin();
        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
            exit;
        }

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $name = trim($data['name'] ?? '');
            $path = trim($data['path'] ?? '');
            $branch = trim($data['target_branch'] ?? '');
            $command = trim($data['build_command'] ?? '');
            $triggers = trim($data['build_triggers'] ?? '');

            $id = (int)($data['id'] ?? 0);
            if (!$id || !$name || !$path || !$branch) {
                echo json_encode(['success' => false, 'error' => 'Некорректные данные для редактирования.']);
                exit;
            }

            $stmt = $connection->prepare("UPDATE {$prefix}environments SET name=?, path=?, target_branch=?, build_command=?, build_triggers=? WHERE id=?");
            $stmt->execute([$name, $path, $branch, $command, $triggers, $id]);

            echo json_encode(['success' => true, 'message' => 'Площадка успешно обновлена.']);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function delete()
    {
        $this->requireAdmin();
        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
            exit;
        }

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $id = (int)($data['id'] ?? 0);
            $stmt = $connection->prepare("DELETE FROM {$prefix}environments WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Площадка удалена.']);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
        }
    }
}