<?php

namespace Autodeployer;

class GitClient
{
    private string $currentGitDir;

    public function __construct(string $currentGitDir)
    {
        $this->currentGitDir = rtrim($currentGitDir, '/');
    }

    public function getCurrentGitDir()
    {
        return $this->currentGitDir;
    }

    private function run(string $command): array
    {
        $fullCommand = sprintf('cd %s && %s 2>&1', escapeshellarg($this->currentGitDir), $command);
        exec($fullCommand, $output, $returnCode);

        return [
            'success' => ($returnCode === 0),
            'output'  => implode("\n", $output),
            'code'    => $returnCode
        ];
    }

    public function getRemoteBranches(): array
    {
        $res = $this->run('git branch -r');
        if (!$res['success']) return [];

        $branches = [];
        foreach (explode("\n", $res['output']) as $line) {
            $line = trim($line);
            if (!$line || strpos($line, '->') !== false) continue;
            $branches[] = str_replace('origin/', '', $line);
        }
        return array_unique($branches);
    }

    public function getCurrentBranch()
    {
        $res = $this->run('git branch | grep "*"');
        if (!$res['success']) return [];

        $result = trim(str_replace("*", "", $res['output']));

        return $result;
    }

    public function fetchAll(): array
    {
        return $this->run('git fetch --all');
    }

    public function stash(): array
    {
        return $this->run('git stash');
    }

    public function checkout(string $branch): array
    {
        return $this->run('git checkout ' . escapeshellarg($branch));
    }

    /**
     * Стягивает код. Если есть конфликт, он повиснет в статусе "unmerged"
     */
    public function pull(string $branch): array
    {
        return $this->run("git pull origin " . escapeshellarg($branch));
    }

    public function push(string $branch): array
    {
        return $this->run("git push origin " . escapeshellarg($branch));
    }

    /**
     * Жесткий сброс всех некоммиченных локальных изменений.
     * Возвращает файлы к состоянию последнего успешного коммита.
     */
    public function resetHard(): array
    {
        return $this->run('git reset --hard');
    }

    /**
     * Коммитит все локальные изменения на сервере
     */
    public function commitLocalChanges(): array
    {
        $this->run('git add --all');
        // Если изменений нет, команда вернет ошибку, это нормально.
        return $this->run('git commit -m "Auto-commit: Changes on production"');
    }

    /**
     * Разрешает конфликты в пользу кода с сервера
     */
    public function resolveConflicts(string $branch): array
    {
        // Выбираем код сервера
        $this->run('git checkout ' . escapeshellarg($branch) . ' --theirs .');
        // Завершаем слияние
        return $this->run('git commit -am "Auto-commit: Remote Conflict Resolved"');
    }

    public function setGitUserConfig()
    {
        $this->run('git config --global user.email "autodeployer@gmail.com"');
        $this->run('git config --global user.name "autodeployer"');
        return $this->run('git config --global --list');
    }

    public function getBranchCommits($branch)
    {
        $res = $this->run("git log $branch  --pretty=format:\"%h - %an, %ar : %s\" -5");
        if (!$res['success']) return [];

        $commits = [];
        foreach (explode("\n", $res['output']) as $line) {
            $commits[] = trim($line);
        }
        return array_unique($commits);
    }
}