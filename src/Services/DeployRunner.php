<?php

namespace Autodeployer\Services;

use Autodeployer\Core\Database;

class DeployRunner
{
    private Database $db;

    private string $logPath = ROOT_PATH . 'logs/';

    public function __construct(Database $db)
    {
        $this->ensureLogDirectoryExists();
        $this->db = $db;
    }

    public function deploy(int $envId, string $source, string $strategy = 'commit', bool $forceBuild = false)
    {
        $this->deleteOldLogs();
        $deployStartDate = date('Y-m-d-H-i-s');
        $deployLogName = $source . '_' . $deployStartDate;
        $log = [];
        $dbPrefix = $this->db->getPrefix();

        $stmt = $this->db->getConnection()->prepare("SELECT * FROM `{$dbPrefix}environments` WHERE id = :id");
        $stmt->execute(['id' => $envId]);
        $environment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$environment) {
            $msg = "Площадка с ID {$envId} не найдена в базе.";
            return ['success' => false, 'log' => $msg];
        }

        $targetPath = rtrim($environment['path'], '/');
        $branch = $environment['target_branch'];
        $envName = $environment['name'];

        $log[] = "[INFO] Начинаем деплой для: {$envName} (Ветка: {$branch})";
        $this->log($deployLogName, $log[array_key_last($log)]);

        $git = new GitClient($targetPath);

        $oldCommit = $git->getCurrentCommitHash();

        $log[] = "Устанавливаем пользователя";
        $this->log($deployLogName, $log[array_key_last($log)]);
        $activeUser = $_SESSION['username'] ?? null;
        $userConfig = $git->setGitUserConfig($activeUser);
        $log[] = $userConfig['output'];
        $this->log($deployLogName, $log[array_key_last($log)]);

        $log[] = "[GIT] git fetch --all...";
        $this->log($deployLogName, $log[array_key_last($log)]);
        $git->fetchAll();

        if ($strategy === 'reset') {
            $log[] = "[GIT] Стратегия RESET: Жесткий сброс локальных правок...";
            $this->log($deployLogName, $log[array_key_last($log)]);
            $res = $git->resetHard();
        } else {
            $log[] = "[GIT] Стратегия COMMIT: Сохраняем локальные правки на сервере...";
            $this->log($deployLogName, $log[array_key_last($log)]);
            $res = $git->commitLocalChanges();
        }
        $log[] = $res['output'];
        $this->log($deployLogName, $log[array_key_last($log)]);

        $log[] = "[GIT] git checkout {$branch}...";
        $this->log($deployLogName, $log[array_key_last($log)]);
        $checkoutResult = $git->checkout($branch);
        $log[] = $checkoutResult['output'];
        $this->log($deployLogName, $log[array_key_last($log)]);

        if (!$checkoutResult['success']) {
            $log[] = "[ERROR] Ошибка при переключении ветки";
            $this->log($deployLogName, $log[array_key_last($log)]);
            return ['success' => false, 'log' => implode("\n", $log)];
        }

        $log[] = "[GIT] git pull origin {$branch}...";
        $this->log($deployLogName, $log[array_key_last($log)]);
        $pullResult = $git->pull($branch);
        $log[] = $pullResult['output'];
        $this->log($deployLogName, $log[array_key_last($log)]);

        if (!$pullResult['success'] && stripos($pullResult['output'], "conflict") !== false) {
            $log[] = "[GIT] Обнаружен конфликт! Решаем автоматически в пользу сервера (--theirs)...";
            $this->log($deployLogName, $log[array_key_last($log)]);
            $resolveResult = $git->resolveConflicts();
            $log[] = $resolveResult['output'];
            $this->log($deployLogName, $log[array_key_last($log)]);

            if (!$resolveResult['success']) {
                $log[] = "[ERROR] Не удалось разрешить конфликты автоматически.";
                $this->log($deployLogName, $log[array_key_last($log)]);
                return ['success' => false, 'log' => implode("\n", $log)];
            }
        } elseif (!$pullResult['success']) {
            $log[] = "[ERROR] Ошибка при выполнении pull.";
            $this->log($deployLogName, $log[array_key_last($log)]);;
            return ['success' => false, 'log' => implode("\n", $log)];
        }


        $log[] = "[GIT] git push {$branch}...";
        $this->log($deployLogName, $log[array_key_last($log)]);
        $pushResult = $git->push($branch);
        $log[] = $pushResult['output'];
        $this->log($deployLogName, $log[array_key_last($log)]);

        if (!empty($environment['build_command'])) {
            $newCommit = $git->getCurrentCommitHash();
            $shouldBuild = $forceBuild;
            $buildTriggers = [];
            if (!empty($environment['build_triggers'])) {
                $buildTriggers = array_filter(array_map('trim', explode(',', $environment['build_triggers'])));
            }

            if (!$shouldBuild && $oldCommit && $newCommit && $oldCommit !== $newCommit) {

                if (empty($buildTriggers)) {
                    $shouldBuild = true;
                    $log[] = "[INFO] Триггеры не заданы. Сборка запускается по умолчанию.";
                    $this->log($deployLogName, $log[array_key_last($log)]);
                } else {
                    $changedFiles = $git->getChangedFiles($oldCommit, $newCommit);

                    foreach ($changedFiles as $file) {
                        foreach ($buildTriggers as $trigger) {
                            if (strpos($file, $trigger) === 0 || $file === $trigger) {
                                $shouldBuild = true;
                                $log[] = "[INFO] Задетекчены изменения в '{$trigger}' (файл: {$file}). Требуется сборка.";
                                $this->log($deployLogName, $log[array_key_last($log)]);
                                break 2;
                            }
                        }
                    }
                }

            } elseif (!$shouldBuild && $oldCommit === $newCommit) {
                $log[] = "[INFO] Коммит не изменился, сборка не требуется.";
                $this->log($deployLogName, $log[array_key_last($log)]);
            }

            if ($shouldBuild) {
                $log[] = "[CMD] Выполняем сборку: {$environment['build_command']}";
                $this->log($deployLogName, $log[array_key_last($log)]);
                $cmd = sprintf('cd %s && %s 2>&1', escapeshellarg($targetPath), $environment['build_command']);
                exec($cmd, $cmdOutput, $cmdCode);
                $log[] = implode("\n", $cmdOutput);
                $this->log($deployLogName, $log[array_key_last($log)]);
            } else {
                $log[] = "[SKIP] Изменений нет. Сборка пропущена.";
                $this->log($deployLogName, $log[array_key_last($log)]);
            }
        }

        $log[] = "[SUCCESS] Деплой успешно завершен!";
        $this->log($deployLogName, $log[array_key_last($log)]);

        return [
            'success' => true,
            'log' => implode("\n", $log)
        ];
    }

    private function log($name, $message)
    {
        if ($name && $message) {
            file_put_contents($this->logPath . $name . '.log', $message . PHP_EOL, FILE_APPEND);
        }
    }

    private function deleteOldLogs()
    {
        $leaveLast = 10;
        $files = glob($this->logPath ."/*.log");
        $count = count($files);
        if ($count > $leaveLast) {
            sort($files);
            $toDelete = array_slice($files, 0, $count - $leaveLast);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }

    private function ensureLogDirectoryExists()
    {
        if (is_dir($this->logPath)) {
            return;
        }

        if (!mkdir($this->logPath, 0775, true) && !is_dir($this->logPath)) {
            throw new \RuntimeException("Нет прав на создание папки для логов: {$this->logPath}");
        }
    }
}
