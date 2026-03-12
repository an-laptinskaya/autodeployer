<?php

namespace Autodeployer;

class DeployRunner
{
    private Database $db;
    private Notifier $notifier;

    public function __construct(Database $db, Notifier $notifier)
    {
        $this->db = $db;
        $this->notifier = $notifier;
    }

    public function deploy(int $envId, string $strategy = 'commit')
    {
        $log = [];
        $dbPrefix = $this->db->getPrefix();

        $stmt = $this->db->getConnection()->prepare("SELECT * FROM `{$dbPrefix}environments` WHERE id = :id");
        $stmt->execute(['id' => $envId]);
        $environment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$environment) {
            $msg = "Площадка с ID {$envId} не найдена в базе.";
//            $this->notifier->send("Ошибка деплоя:\n" . $msg, true);
            return ['success' => false, 'log' => $msg];
        }

        $targetPath = rtrim($environment['path'], '/');
        $branch = $environment['target_branch'];
        $envName = $environment['name'];

        $log[] = "[INFO] Начинаем деплой для: {$envName} (Ветка: {$branch})";

        // Инициализируем GitClient ИМЕННО для папки этой площадки
        $git = new GitClient($targetPath);

        // Стягиваем информацию об изменениях с сервера
        $log[] = "[GIT] git fetch --all...";
        $git->fetchAll();

        if ($strategy === 'reset') {
            $log[] = "[GIT] Стратегия RESET: Жесткий сброс локальных правок...";
            $res = $git->resetHard();
        } elseif ($strategy === 'stash') {
            $log[] = "[GIT] Стратегия STASH: Прячем локальные правки в карман...";
            $res = $git->stash();
        } else {
            // По умолчанию работает логика "как в sh.git" (commit)
            $log[] = "[GIT] Стратегия COMMIT: Сохраняем локальные правки на сервере...";
            $res = $git->commitLocalChanges();
        }
        $log[] = $res['output'];

        // Переключаемся на нужную ветку
        $log[] = "[GIT] git checkout {$branch}...";
        $checkoutResult = $git->checkout($branch);
        $log[] = $checkoutResult['output'];

        if (!$checkoutResult['success']) {
            $this->failDeploy($envName, $branch, $log, "Ошибка при переключении ветки");
            return ['success' => false, 'log' => implode("\n", $log)];
        }

        // Обновляем код
        $log[] = "[GIT] git pull origin {$branch}...";
        $pullResult = $git->pull($branch);
        $log[] = $pullResult['output'];

        if (!$pullResult['success']) {
            $this->failDeploy($envName, $branch, $log, "Ошибка при выполнении pull");
            return ['success' => false, 'log' => implode("\n", $log)];
        }



        // ОБРАБОТКА КОНФЛИКТОВ (только для режима commit)
        if ($strategy === 'commit' && !$pullResult['success'] && strpos($pullResult['output'], 'Conflict') !== false) {
            $log[] = "[GIT] Обнаружен конфликт! Решаем автоматически в пользу сервера (--theirs)...";
            $resolveResult = $git->resolveConflicts();
            $log[] = $resolveResult['output'];

            if (!$resolveResult['success']) {
                $this->failDeploy($envName, $branch, $log, "Не удалось разрешить конфликты автоматически.");
                return ['success' => false, 'log' => implode("\n", $log)];
            }
        } elseif (!$pullResult['success']) {
            $this->failDeploy($envName, $branch, $log, "Ошибка при выполнении pull.");
            return ['success' => false, 'log' => implode("\n", $log)];
        }




        // Выполнение пост-команд (например, npm ci, сброс кэша)
        if (!empty($environment['build_command'])) {
            $log[] = "[CMD] Выполняем: {$environment['build_command']}";
            $cmd = sprintf('cd %s && %s 2>&1', escapeshellarg($targetPath), $environment['build_command']);
            exec($cmd, $cmdOutput, $cmdCode);
            $log[] = implode("\n", $cmdOutput);
        }

        $log[] = "[SUCCESS] Деплой успешно завершен!";

        // Отправляем успешное уведомление
//        $this->notifier->send("<b>Деплой успешно завершен!</b> 🎉\nПлощадка: {$envName}\nВетка: {$branch}");

        return [
            'success' => true,
            'log' => implode("\n", $log)
        ];
    }

    public function deploy2(int $envId, string $strategy = 'commit')
    {
        $log = [];
        $dbPrefix = $this->db->getPrefix();

        $stmt = $this->db->getConnection()->prepare("SELECT * FROM `{$dbPrefix}environments` WHERE id = :id");
        $stmt->execute(['id' => $envId]);
        $environment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$environment) {
            $msg = "Площадка с ID {$envId} не найдена в базе.";
//            $this->notifier->send("Ошибка деплоя:\n" . $msg, true);
            return ['success' => false, 'log' => $msg];
        }

        $targetPath = rtrim($environment['path'], '/');
        $branch = $environment['target_branch'];
        $envName = $environment['name'];

        $log[] = "[INFO] Начинаем деплой для: {$envName} (Ветка: {$branch})";

        // Инициализируем GitClient ИМЕННО для папки этой площадки
        $git = new GitClient($targetPath);

        if ($strategy === 'reset') {
            $log[] = "[GIT] Стратегия RESET: Жесткий сброс локальных правок...";
            $res = $git->resetHard();
            $log[] = $res['output'];
        } else {
            // По умолчанию работает логика "как в sh.git" (commit)
            $log[] = "[GIT] Стратегия COMMIT: Сохраняем локальные правки на сервере...";
            $res = $git->commitLocalChanges();
            $log[] = $res['output'];
        }

        // Обновляем код
        $log[] = "[GIT] git pull origin {$branch}...";
        $pullResult = $git->pull($branch);
        $log[] = $pullResult['output'];
        if (!$pullResult['success']) {
            $this->failDeploy($envName, $branch, $log, "Ошибка при выполнении pull");
            return ['success' => false, 'log' => implode("\n", $log)];
        }

        if ($strategy === 'reset') {
            // Переключаемся на нужную ветку
            $log[] = "[GIT] git checkout {$branch}...";
            $checkoutResult = $git->checkout($branch);
            $log[] = $checkoutResult['output'];
        } else {
            // Переключаемся на нужную ветку
            $log[] = "[GIT] git checkout {$branch}...";
            $checkoutResult = $git->resolveConflicts($branch);
            $log[] = $checkoutResult['output'];
        }

        if (!$checkoutResult['success']) {
            $this->failDeploy($envName, $branch, $log, "Ошибка при переключении ветки");
            return ['success' => false, 'log' => implode("\n", $log)];
        }

        $log[] = "[GIT] git push origin {$branch}...";
        $pushResult = $git->push($branch);
        $log[] = $pushResult['output'];
        if (!$pushResult['success']) {
            $this->failDeploy($envName, $branch, $log, "Ошибка при выполнении push");
            return ['success' => false, 'log' => implode("\n", $log)];
        }


        // Стягиваем информацию об изменениях с сервера
        $log[] = "[GIT] git fetch --all...";
        $git->fetchAll();

        // ОБРАБОТКА КОНФЛИКТОВ (только для режима commit)
        if ($strategy === 'commit' && !$pullResult['success'] && strpos($pullResult['output'], 'Conflict') !== false) {
            $log[] = "[GIT] Обнаружен конфликт! Решаем автоматически в пользу сервера (--theirs)...";
            $resolveResult = $git->resolveConflicts();
            $log[] = $resolveResult['output'];

            if (!$resolveResult['success']) {
                $this->failDeploy($envName, $branch, $log, "Не удалось разрешить конфликты автоматически.");
                return ['success' => false, 'log' => implode("\n", $log)];
            }
        } elseif (!$pullResult['success']) {
            $this->failDeploy($envName, $branch, $log, "Ошибка при выполнении pull.");
            return ['success' => false, 'log' => implode("\n", $log)];
        }


        // Выполнение пост-команд (например, npm ci, сброс кэша)
        if (!empty($environment['build_command'])) {
            $log[] = "[CMD] Выполняем: {$environment['build_command']}";
            $cmd = sprintf('cd %s && %s 2>&1', escapeshellarg($targetPath), $environment['build_command']);
            exec($cmd, $cmdOutput, $cmdCode);
            $log[] = implode("\n", $cmdOutput);
        }

        $log[] = "[SUCCESS] Деплой успешно завершен!";

        // Отправляем успешное уведомление
//        $this->notifier->send("<b>Деплой успешно завершен!</b> 🎉\nПлощадка: {$envName}\nВетка: {$branch}");

        return [
            'success' => true,
            'log' => implode("\n", $log)
        ];
    }

    private function failDeploy(string $envName, string $branch, array $log, string $errorReason): void
    {
        $log[] = "[ERROR] " . $errorReason;
        $errorText = implode("\n", $log);
//        $this->notifier->send(
//            "<b>🚨 Ошибка деплоя!</b>\nПлощадка: {$envName}\nВетка: {$branch}\n\n<pre>{$errorText}</pre>",
//            true
//        );
    }
}