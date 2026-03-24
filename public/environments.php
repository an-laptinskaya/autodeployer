<?php

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die("Доступ запрещен. Эта страница только для администраторов.");
}

$config = require ROOT_PATH . 'config/config.php';
$db = new Autodeployer\Database($config);
$stmt = $db->getConnection()->query("SELECT * FROM {$db->getPrefix()}environments ORDER BY id ASC");
$environments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление площадками</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
</head>
<body>

<div class="nav-bar">
    <div class="nav-links">
        <span style="font-size: 18px; font-weight: bold; color: #fff; margin-right: 20px;">AutoDeployer</span>
        <a href="<?= BASE_URL ?>?page=branches">Площадки</a>
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="<?= BASE_URL ?>?page=users">Пользователи</a>
            <a href="<?= BASE_URL ?>?page=environments" class="active">Настройки площадок</a>
            <a href="<?= BASE_URL ?>?page=webhook">Настройки Webhook</a>
        <?php endif; ?>
    </div>
    <div>
        <?= htmlspecialchars($_SESSION['username']) ?>
        <a href="<?= BASE_URL ?>?page=logout" style="color: #ffcccc; margin-left: 15px; text-decoration: none;">Выйти</a>
    </div>
</div>

<div class="card-container">
    <div class="card">
        <h2>Добавить новую площадку</h2>
        <div id="addAlert" class="alert"></div>
        <form id="addEnvForm" onsubmit="saveEnv(event, 'add')" class="form-grid">
            <div class="form-group">
                <label>Название площадки * (напр. Песочница Васи)</label>
                <input type="text" id="addName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Абсолютный путь на сервере * (напр. /var/www/sandbox)</label>
                <input type="text" id="addPath" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Отслеживаемая ветка * (напр. master)</label>
                <input type="text" id="addBranch" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Команда сборки (напр. npm run build)</label>
                <input type="text" id="addCommand" class="form-control" placeholder="Можно оставить пустым">
            </div>
            <div class="form-group col-span-2">
                <label>Триггеры сборки (папки через запятую, напр. markup/, src/)</label>
                <input type="text" id="addTriggers" class="form-control" placeholder="Можно оставить пустым">
            </div>
            <div class="col-span-2">
                <button type="submit" id="addBtn" class="btn btn-primary">Добавить площадку</button>
                <p class="help-text">* Не забудьте инициализировать Git (git init) в указанной папке перед деплоем!</p>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Существующие площадки</h2>
        <table>
            <tr>
                <th class="col-id">ID</th>
                <th>Название</th>
                <th>Путь</th>
                <th>Ветка</th>
                <th>Сборка</th>
                <th class="col-actions">Действия</th>
            </tr>
            <?php foreach ($environments as $env): ?>
                <tr>
                    <td><?= $env['id'] ?></td>
                    <td class="cell-name"><?= htmlspecialchars($env['name']) ?></td>
                    <td class="cell-path"><?= htmlspecialchars($env['path']) ?></td>
                    <td><span class="badge-branch"><?= htmlspecialchars($env['target_branch']) ?></span></td>
                    <td>
                        <?php if ($env['build_command']): ?>
                            <div class="build-cmd"><?= htmlspecialchars($env['build_command']) ?></div>
                            <div class="build-triggers">Триггеры: <?= htmlspecialchars($env['build_triggers'] ?: 'Все файлы') ?></div>
                        <?php else: ?>
                            <span class="text-empty">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td class="cell-actions">
                        <button class="btn btn-change btn-sm" onclick='openEditModal(<?= json_encode($env, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Изменить</button>
                        <button class="btn btn-delete btn-sm" onclick="deleteEnv(<?= $env['id'] ?>, '<?= htmlspecialchars($env['name'], ENT_QUOTES) ?>')">Удалить</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div id="editModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">Редактировать площадку</div>
        <div id="editAlert" class="alert"></div>
        <form onsubmit="saveEnv(event, 'edit')">
            <input type="hidden" id="editId">
            <div class="form-group">
                <label>Название площадки</label>
                <input type="text" id="editName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Абсолютный путь на сервере</label>
                <input type="text" id="editPath" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Отслеживаемая ветка</label>
                <input type="text" id="editBranch" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Команда сборки</label>
                <input type="text" id="editCommand" class="form-control">
            </div>
            <div class="form-group">
                <label>Триггеры сборки (через запятую)</label>
                <input type="text" id="editTriggers" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Отмена</button>
                <button type="submit" id="editBtn" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>public/assets/js/script.js"></script>

</body>
</html>