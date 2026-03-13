<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/GitClient.php';

$config = require __DIR__ . '/../config/config.php';

$db = new Autodeployer\Database($config);

$dbPrefix = $db->getPrefix();
$envId = 1;
$stmt = $db->getConnection()->prepare("SELECT * FROM `{$dbPrefix}environments` WHERE id = :id");
$stmt->execute(['id' => $envId]);
$environment = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$environment) {
    die("Площадка не найдена.");
}

$git = new \Autodeployer\GitClient($environment['path']);

$result = [];

// Текущая ветка, которая записана в базе
$currentBranch = $environment['target_branch'];

// Получаем список веток с сервера (origin)
$branches = $git->getRemoteBranches();
foreach ($branches as $branch) {
    $result[] = [
        'name' => $branch,
        'commits' => $git->getBranchCommits($branch),
        'isCurrentBranch' => $currentBranch === $branch,
    ];
}

echo '<pre>';
var_dump($branches);
var_dump($currentBranch);
var_dump($result);

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление деплоем</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f4f9; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        select, button { padding: 10px; margin-top: 10px; font-size: 16px; }
        button { background: #bddcff; color: black; border: none; border-radius: 4px; cursor: pointer; padding: 8px; }
        button:hover { background: #0056b3; }
        .branch-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Площадка: <?= htmlspecialchars($environment['name']) ?></h2>
    <p><b>Путь:</b> <?= htmlspecialchars($environment['path']) ?></p>

    <?php foreach ($result as $branch) : ?>
        <div class="branch-row">
            <div><?= $branch['name'] ?></div>
            <div>
                <?php if (is_array($branch['commits'])) :?>
                    <?php foreach ($branch['commits'] as $commit) : ?>
                        <div><?= $commit ?></div>
                    <?php endforeach ; ?>
                <?php endif ;?>
            </div>
            <div><?= $branch['isCurrentBranch'] ? 'Да' : 'Нет' ?></div>
            <div>
                <button
                        type="button"
                    <?= $branch['isCurrentBranch'] ? 'disabled' : '' ?>
                        onclick="changeBranch(<?= $envId ?>, '<?= $branch['name'] ?>')"
                >Сменить ветку</button>
                <button
                        type="button"
                    <?= $branch['isCurrentBranch'] ? 'disabled' : '' ?>
                        onclick="changeBranch(<?= $envId ?>, '<?= $branch['name'] ?>', 'reset')"
                >Сменить ветку со сбросом изменений</button>
            </div>
        </div>
    <?php endforeach ; ?>


    <form action="change_branch.php" method="POST">
        <input type="hidden" name="environment_id" value="<?= $environment['id'] ?>">

        <label for="branch"><b>Выберите ветку для деплоя:</b></label><br>
        <select name="branch" id="branch">
            <?php foreach ($branches as $branch): ?>
                <option value="<?= htmlspecialchars($branch) ?>" <?= ($branch === $currentBranch) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($branch) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <br>
        <button type="submit">Сменить ветку</button>
    </form>
</div>

</body>
</html>

<script>

    async function changeBranch(envId, branchName, strategy = 'commit') {
        console.log(envId);
        console.log(branchName);
        console.log(strategy);
        if (!branchName) {
            return;
        }

        const response = await fetch('/autodeployer/api/change_branch.php', {
            method: 'POST',
            body: JSON.stringify({
                envId: envId,
                branchName: branchName,
                strategy: strategy
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        console.log(result);
    }

</script>