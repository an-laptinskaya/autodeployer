<?php

namespace Autodeployer\Controllers;

use Autodeployer\Services\DeployRunner;

class WebhookController extends BaseController
{
    private static $settingsFields = [
        'setting_key',
        'setting_value',
    ];

    private static $envFields = ['id', 'name'];

    public function index()
    {
        $this->requireAdmin();
        $this->render('webhook', []);
    }

    public function generateToken()
    {
        $this->requireAdmin();
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $newToken = bin2hex(random_bytes(16));

            $this->db->verifyColumns('settings', self::$settingsFields);
            $stmt = $connection->query("SELECT COUNT(*) FROM {$prefix}settings WHERE setting_key = 'webhook_token'");

            if ($stmt->fetchColumn() > 0) {
                $updateStmt = $connection->prepare("UPDATE {$prefix}settings SET setting_value = ? WHERE setting_key = 'webhook_token'");
                $updateStmt->execute([$newToken]);
            } else {
                $insertStmt = $connection->prepare("INSERT INTO {$prefix}settings (setting_key, setting_value) VALUES ('webhook_token', ?)");
                $insertStmt->execute([$newToken]);
            }

            echo json_encode(['success' => true, 'token' => $newToken]);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
        }
    }

    public function handleIncomingWebhook()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
        $headers = array_change_key_case($headers, CASE_UPPER);
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if (!$data) {
            http_response_code(400);
            die("Некорректные данные вебхука");
        }

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $columns = $this->db->prepareColumns('settings', ['setting_value']);
            $stmt = $connection->query("SELECT {$columns} FROM `{$prefix}settings` WHERE setting_key = 'webhook_token'");
            $webhookToken = $stmt->fetchColumn();
        } catch (\Exception $e) {
            die("Ошибка: " . $e->getMessage());
        }
        if ($webhookToken === false) {
            die("Токен вебхука не настроен в базе данных");
        }

        $platform = 'unknown';
        $branch = '';

        if (isset($headers['X-GITHUB-EVENT'])) {
            $platform = 'github';

            $signature = $headers['X-HUB-SIGNATURE-256'] ?? '';
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookToken);
            if (!hash_equals($expectedSignature, $signature)) {
                http_response_code(403);
                die("Неверная подпись GitHub");
            }

            if ($headers['X-GITHUB-EVENT'] === 'push' && isset($data['ref'])) {
                $branch = str_replace('refs/heads/', '', $data['ref']);
            }

        } elseif (isset($headers['X-GITLAB-EVENT'])) {
            $platform = 'gitlab';

            $token = $headers['X-GITLAB-TOKEN'] ?? '';
            if ($token !== $webhookToken) {
                http_response_code(403);
                die("Неверный токен GitLab");
            }

            if ($headers['X-GITLAB-EVENT'] === 'Push Hook' && isset($data['ref'])) {
                $branch = str_replace('refs/heads/', '', $data['ref']);
            }
        } elseif (isset($headers['X-EVENT-KEY'])) {
            $platform = 'bitbucket';

            $tokenFromUrl = $_GET['token'] ?? '';
            $signature = $headers['X-HUB-SIGNATURE-256'] ?? $headers['X-HUB-SIGNATURE'] ?? '';
            $isAuthenticated = false;

            if ($tokenFromUrl === $webhookToken) {
                $isAuthenticated = true;
            } elseif (!empty($signature)) {
                $algo = strpos($signature, 'sha256=') === 0 ? 'sha256' : 'sha1';
                $expectedSignature = $algo . '=' . hash_hmac($algo, $payload, $webhookToken);
                if (hash_equals($expectedSignature, $signature)) {
                    $isAuthenticated = true;
                }
            }

            if (!$isAuthenticated) {
                http_response_code(403);
                die("Ошибка аутентификации Bitbucket. Передайте валидный ?token= в URL или правильный заголовок X-Hub-Signature");
            }

            if ($headers['X-EVENT-KEY'] === 'repo:push' && !empty($data['push']['changes'])) {
                foreach ($data['push']['changes'] as $change) {
                    if (isset($change['new']['type']) && $change['new']['type'] === 'branch' && !empty($change['new']['name'])) {
                        $branch = $change['new']['name'];
                        break;
                    }
                }
            }

        } else {
            http_response_code(400);
            die("Неизвестная платформа");
        }

        if (empty($branch)) {
            http_response_code(200);
            die("Не является push-событием, либо ветка не найдена.");
        }

        try {
            $columns = $this->db->prepareColumns('environments', self::$envFields);
            $stmt = $connection->prepare("SELECT {$columns} FROM `{$prefix}environments` WHERE target_branch = :branch");
            $stmt->execute(['branch' => $branch]);
            $environments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($environments)) {
                http_response_code(200);
                die("Нет площадок, отслеживающих ветку: {$branch}");
            }

            $dep = new DeployRunner($this->db);

            foreach ($environments as $env) {
                $result = $dep->deploy($env['id'], 'auto');
            }

            http_response_code(200);
            echo "Вебхук из {$platform} успешно обработан для ветки: {$branch}";

        } catch (\Exception $e) {
            http_response_code(500);
            die("Ошибка: " . $e->getMessage());
        }
    }
}