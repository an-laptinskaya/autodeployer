<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вход — AutoDeployer</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-header">
        <h2>Авторизация</h2>
        <span>AutoDeployer</span>
    </div>

    <div id="addAlert" class="alert alert-error"></div>

    <form onsubmit="login(event)">
        <div class="form-group">
            <label for="username">Логин</label>
            <input id="username" type="text" class="form-control" name="username" placeholder="Введите логин" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input id="password" type="password" class="form-control" name="password" placeholder="Введите пароль" required>
        </div>

        <button type="submit" id="submitBtn" class="btn-login">Войти</button>
    </form>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/script.js"></script>

</body>
</html>