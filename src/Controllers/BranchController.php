<?php

namespace Autodeployer\Controllers;

use Autodeployer\Services\DeployRunner;
use Autodeployer\Services\GitClient;
use Autodeployer\Services\Notifier;

class BranchController extends BaseController
{
    private static array $envSelectFields = [
        'id',
        'name',
        'path',
        'target_branch'
    ];

    private static array $envFetchFields = ['path'];
    private static array $envDeployFields = ['path', 'target_branch'];

    public function index()
    {
        $this->requireLogin();
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $columns = $this->db->prepareColumns('environments', self::$envSelectFields);
            $stmt = $connection->query("SELECT {$columns} FROM `{$prefix}environments`");
            $environments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            die("Ошибка: " . $e->getMessage());
        }

        $resultEnvs = [];

        foreach ($environments as $environment) {
            $git = new GitClient($environment['path']);
            $notifier = new Notifier();
            $alerts = $notifier->getEnvironmentAlerts($git);
            $branchesData = [];
            $currentBranch = $environment['target_branch'];
            $branches = $git->getRemoteBranches();

            foreach ($branches as $branch) {
                $commits = $git->getBranchCommits($branch);
                $prettyCommits = [];
                foreach ($commits as $commit) {
                    $hash = '';
                    $msg = $commit;
                    $author = '';
                    $time = '';
                    if (preg_match('/^([a-f0-9]+)\s+-\s+([^,]+),\s+(.*?)\s+:\s+(.*)$/', $commit, $m)) {
                        $hash = $m[1];
                        $author = $m[2];
                        $time = $m[3];
                        $msg = $m[4];
                    }
                    $prettyCommits[] = [
                        'hash' => $hash,
                        'author' => $author,
                        'time' => $time,
                        'msg' => $msg,
                    ];
                }

                $branchesData[] = [
                    'name' => $branch,
                    'commits' => $prettyCommits,
                    'isCurrentBranch' => $currentBranch === $branch,
                ];
            }

            $resultEnvs[] = [
                'id' => $environment['id'],
                'name' => $environment['name'],
                'path' => $environment['path'],
                'target_branch' => $currentBranch,
                'branches' => $branchesData,
                'alerts' => $alerts,
            ];
        }

        $this->render('branches', [
            'resultEnvs' => $resultEnvs,
        ]);
    }

    public function fetch()
    {
        $this->requireLogin();
        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $columns = $this->db->prepareColumns('environments', self::$envFetchFields);
            $stmt = $connection->prepare("SELECT {$columns} FROM `{$prefix}environments` WHERE id = :id");
            $stmt->execute(['id' => $data['envId']]);
            $environment = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$environment) {
                echo json_encode(['success' => false, 'error' => 'Площадка не найдена']);
                exit;
            }

            $git = new GitClient($environment['path']);
            $result = $git->fetchAll();

            if ($result['success']) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['output']]);
            }

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function deploy()
    {
        $this->requireLogin();
        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();

        try {
            $columns = $this->db->prepareColumns('environments', self::$envDeployFields);
            $stmt = $connection->prepare("SELECT {$columns} FROM `{$prefix}environments` WHERE id = :id");
            $stmt->execute(['id' => $data['envId']]);
            $environment = $stmt->fetch(\PDO::FETCH_ASSOC);
            $oldBranch = $environment['target_branch'];

            $dep = new DeployRunner($this->db);
            $this->updateEnvironmentBranch($data['envId'], $data['branchName']);
            $result = $dep->deploy($data['envId'], 'handle', $data['strategy']);

            $git = new GitClient($environment['path']);
            $actualBranch = $git->getCurrentBranch();

            if (!$result['success'] && $oldBranch !== $actualBranch) {
                $this->updateEnvironmentBranch($data['envId'], $actualBranch);
                $result['log'] .= "\n[INFO] База данных откачена к ветке: {$actualBranch}";
            }

            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'log' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    private function updateEnvironmentBranch(int $id, string $branch): bool
    {
        $connection = $this->db->getConnection();
        $prefix = $this->db->getPrefix();
        $this->db->verifyColumns('environments', ['target_branch']);
        $stmt = $connection->prepare("
            UPDATE {$prefix}environments 
            SET `target_branch` = :branch 
            WHERE `id` = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'branch' => $branch
        ]);
    }
}