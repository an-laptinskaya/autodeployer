<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Настройки Webhook</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<div class="nav-bar">
    <div class="nav-links">
        <span style="font-size: 18px; font-weight: bold; color: #fff; margin-right: 20px;">AutoDeployer</span>
        <a href="<?= BASE_URL ?>?page=branches">Площадки</a>
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="<?= BASE_URL ?>?page=users">Пользователи</a>
            <a href="<?= BASE_URL ?>?page=environments">Настройки площадок</a>
            <a href="<?= BASE_URL ?>?page=webhook" class="active">Настройки Webhook</a>
        <?php endif; ?>
    </div>
    <div>
        <?= htmlspecialchars($_SESSION['username']) ?>
        <a href="<?= BASE_URL ?>?page=logout" style="color: #ffcccc; margin-left: 15px; text-decoration: none;">Выйти</a>
    </div>
</div>

<div class="card-container">
    <div class="card">
        <h2 style="margin-top: 0;">Генерация Webhook Токена</h2>
        <p>Токен необходим для обеспечения безопасности при приеме вебхуков от GitHub, GitLab или Bitbucket. Вставьте его в настройки вебхука в вашем репозитории (в поле Secret Token).</p>
        <p class="warning-text">Внимание! При генерации нового токена старый будет навсегда стерт. Все старые вебхуки перестанут работать до обновления в них токена.</p>

        <button id="generateBtn" class="btn btn-warning" onclick="generateToken()">Сгенерировать новый токен</button>

        <div id="resultBox" class="alert"></div>
    </div>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/script.js"></script>

</body>
</html>