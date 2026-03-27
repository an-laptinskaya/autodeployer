<?php

namespace Autodeployer\Controllers;

use Autodeployer\Services\DeployRunner;

class WebhookController extends BaseController
{
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
        $headers = getallheaders();
        $headers = array_change_key_case($headers, CASE_UPPER);
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if (!$data) {
            http_response_code(400);
            die("Invalid payload");
        }

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        $stmt = $connection->query("SELECT `setting_value` FROM `{$prefix}settings` WHERE setting_key = 'webhook_token'");
        $webhookToken = $stmt->fetchColumn();
        if ($webhookToken === false) {
            die("There is no webhook token in the database table");
        }

        $platform = 'unknown';
        $branch = '';

        if (isset($headers['X-GITHUB-EVENT'])) {
            $platform = 'github';

            $signature = $headers['X-HUB-SIGNATURE-256'] ?? '';
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookToken);
            if (!hash_equals($expectedSignature, $signature)) {
                http_response_code(403);
                die("GitHub signature mismatch");
            }

            if ($headers['X-GITHUB-EVENT'] === 'push' && isset($data['ref'])) {
                $branch = str_replace('refs/heads/', '', $data['ref']);
            }

        } elseif (isset($headers['X-GITLAB-EVENT'])) {
            $platform = 'gitlab';

            $token = $headers['X-GITLAB-TOKEN'] ?? '';
            if ($token !== $webhookToken) {
                http_response_code(403);
                die("GitLab token mismatch");
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
                die("Bitbucket authentication failed. Please provide a valid ?token= in the URL or a valid X-Hub-Signature.");
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
            die("Unknown platform");
        }

        if (empty($branch)) {
            http_response_code(200);
            die("Not a push event or branch not found. Ignored.");
        }

        try {

            $stmt = $connection->prepare("SELECT id, name FROM `{$prefix}environments` WHERE target_branch = :branch");
            $stmt->execute(['branch' => $branch]);
            $environments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($environments)) {
                http_response_code(200);
                die("No environments listening to branch: {$branch}");
            }

            $dep = new DeployRunner($this->db);

            foreach ($environments as $env) {
                $result = $dep->deploy($env['id'], 'auto');
            }

            http_response_code(200);
            echo "Webhook processed successfully for branch: {$branch}";

        } catch (\Exception $e) {
            http_response_code(500);
            die("Error: " . $e->getMessage());
        }
    }
}