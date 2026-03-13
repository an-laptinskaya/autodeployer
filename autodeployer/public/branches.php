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

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление деплоем</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f4f9; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        button { background: #bddcff; color: black; border: none; border-radius: 4px; cursor: pointer; padding: 8px; }
        button:hover { background: #6fb5ff; }
        .branch-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        /* Стили для модального окна */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex; justify-content: center; align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: #fff; padding: 25px; border-radius: 8px;
            width: 80%; max-width: 800px; max-height: 90vh;
            display: flex; flex-direction: column; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-header { font-size: 20px; font-weight: bold; margin-bottom: 15px; }

        /* Стили для вывода логов (как в терминале) */
        .terminal-log {
            background: #1e1e1e; color: #00ff00;
            padding: 15px; border-radius: 5px; font-family: monospace;
            overflow-y: auto; max-height: 60vh; white-space: pre-wrap;
            margin-bottom: 15px; font-size: 14px; line-height: 1.4;
        }

        /* Анимация загрузки (спиннер) */
        .loader {
            border: 4px solid #f3f3f3; border-top: 4px solid #3498db;
            border-radius: 50%; width: 30px; height: 30px;
            animation: spin 1s linear infinite; margin: 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Кнопка закрытия */
        .btn-close {
            background: #28a745; color: white; align-self: flex-end; padding: 10px 20px;
            font-size: 16px; border: none; border-radius: 4px; cursor: pointer;
        }
        .btn-close:hover { background: #218838; }
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
</div>
<div id="deployModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div id="modalTitle" class="modal-header">Переключение ветки...</div>

        <div id="deployLoader" class="loader"></div>

        <div id="deployLog" class="terminal-log" style="display: none;"></div>

        <button id="closeModalBtn" class="btn-close" style="display: none;" onclick="closeAndRefresh()">
            Закрыть и обновить данные
        </button>
    </div>
</div>
</body>
</html>

<script>

    async function changeBranch(envId, branchName, strategy = 'commit') {
        if (!branchName) return;

        // 1. Получаем элементы модального окна
        const modal = document.getElementById('deployModal');
        const title = document.getElementById('modalTitle');
        const loader = document.getElementById('deployLoader');
        const logBox = document.getElementById('deployLog');
        const closeBtn = document.getElementById('closeModalBtn');

        // 2. Показываем окно со спиннером
        modal.style.display = 'flex';
        title.innerText = `Деплой ветки: ${branchName} (Стратегия: ${strategy})...`;
        title.style.color = '#333';
        loader.style.display = 'block';
        logBox.style.display = 'none';
        closeBtn.style.display = 'none';

        try {
            // 3. Отправляем запрос на бэкенд
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

            // 4. Скрываем спиннер, показываем логи
            loader.style.display = 'none';
            logBox.style.display = 'block';
            closeBtn.style.display = 'block';

            // Выводим текст лога
            logBox.innerText = result.log || 'Нет логов от сервера.';

            // Меняем заголовок в зависимости от успеха
            if (result.success) {
                title.innerText = `✅ Ветка ${branchName} успешно развернута!`;
                title.style.color = '#28a745'; // Зеленый
            } else {
                title.innerText = `❌ Ошибка деплоя ветки ${branchName}`;
                title.style.color = '#dc3545'; // Красный
            }

        } catch (error) {
            // Если упал сам JS или сервер ответил 500 ошибкой
            loader.style.display = 'none';
            logBox.style.display = 'block';
            closeBtn.style.display = 'block';

            title.innerText = `❌ Системная ошибка`;
            title.style.color = '#dc3545';
            logBox.innerText = `Произошла ошибка при выполнении запроса:\n${error.message}`;
        }
    }

    // Функция закрытия окна и перезагрузки страницы
    function closeAndRefresh() {
        document.getElementById('deployModal').style.display = 'none';
        // Перезагрузка страницы обновит таблицу, коммиты и статус "Текущая"
        window.location.reload();
    }
</script>