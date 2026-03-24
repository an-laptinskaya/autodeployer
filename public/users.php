<?php

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die("Доступ запрещен. Эта страница только для администраторов.");
}

$config = require ROOT_PATH . 'config/config.php';
$db = new Autodeployer\Database($config);
$stmt = $db->getConnection()->query("SELECT id, login, is_admin FROM {$db->getPrefix()}users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
</head>
<body>

<div class="nav-bar">
    <div class="nav-links">
        <span style="font-size: 18px; font-weight: bold; color: #fff; margin-right: 20px;">AutoDeployer</span>
        <a href="<?= BASE_URL ?>?page=branches">Площадки</a>
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="<?= BASE_URL ?>?page=users" class="active">Пользователи</a>
            <a href="<?= BASE_URL ?>?page=environments">Настройки площадок</a>
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
        <div class="card-header">
            <div>
                <div class="card-header-name">Добавить пользователя</div>
                <div id="addAlert" class="alert"></div>
            </div>
        </div>

        <div class="card-body">
            <div class="card-row">
                <form id="addUserForm" onsubmit="addUser(event)" class="user-form">
                    <div>
                        <label style="display: block; margin-bottom: 5px;">Логин:</label>
                        <input type="text" id="newLogin" class="user-form-input" required>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 5px;">Пароль:</label>
                        <input type="text" id="newPassword" class="user-form-input" required>
                    </div>

                    <div style="padding-bottom: 8px;">
                        <label style="cursor: pointer;">
                            <input type="checkbox" id="newIsAdmin" value="1"> Администратор
                        </label>
                    </div>

                    <button type="submit" id="addBtn" class="btn btn-primary">Добавить</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Список пользователей</h2>
        <table>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Логин</th>
                <th>Роль</th>
                <th style="width: 100px;">Действия</th>
            </tr>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td style="font-weight: bold;"><?= htmlspecialchars($u['login']) ?></td>
                    <td>
                        <?php if ($u['is_admin']): ?>
                            <span style="color: green; font-weight: bold;">Администратор</span>
                        <?php else: ?>
                            <span style="color: #666;">Разработчик</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <button type="button" class="btn btn-delete" style="padding: 5px 10px; font-size: 12px;" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['login']) ?>')">Удалить</button>
                        <?php else: ?>
                            <span style="color: #ccc; font-size: 12px;">Это вы</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>public/assets/js/script.js"></script>

</body>
</html>