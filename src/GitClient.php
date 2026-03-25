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
        return $this->run('git fetch --all --prune');
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
     * Жесткий сброс всех некоммиченных локальных изменений
     * Возвращает файлы к состоянию последнего успешного коммита
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
    public function resolveConflicts(): array
    {
        $this->run('git checkout --theirs .');
        return $this->run('git commit -am "Auto-commit: Remote Conflict Resolved"');
    }

    public function setGitUserConfig(string $username = null)
    {
        $name = $username ?: 'autodeployer';
        $email = $username ? ($username . '@gmail.com') : 'autodeployer@gmail.com';
        $this->run('git config user.email ' . escapeshellarg($email));
        $this->run('git config user.name ' . escapeshellarg($name));
        return $this->run('git config --list');
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

    /**
     * Получает хэш текущего коммита
     */
    public function getCurrentCommitHash(): ?string
    {
        $res = $this->run('git rev-parse HEAD');
        return $res['success'] ? trim($res['output']) : null;
    }

    /**
     * Возвращает массив путей к файлам, которые изменились между коммитами
     */
    public function getChangedFiles(string $oldCommit, string $newCommit): array
    {
        $command = sprintf('git diff --name-only %s %s', escapeshellarg($oldCommit), escapeshellarg($newCommit));
        $res = $this->run($command);

        if (!$res['success'] || empty(trim($res['output']))) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $res['output'])));
    }

    /**
     * Возвращает массив незакомиченных файлов
     */
    public function getUncommittedFiles(): array
    {
        $res = $this->run('git status --porcelain');

        if (!$res['success'] || empty(trim($res['output']))) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $res['output'])));
    }
}