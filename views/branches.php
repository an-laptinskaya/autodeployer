<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление деплоем</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>

<div class="nav-bar">
    <div class="nav-links">
        <span style="font-size: 18px; font-weight: bold; color: #fff; margin-right: 20px;">AutoDeployer</span>
        <a href="<?php echo BASE_URL; ?>?page=branches" class="active">Площадки</a>
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="<?php echo BASE_URL; ?>?page=users">Пользователи</a>
            <a href="<?php echo BASE_URL; ?>?page=environments">Настройки площадок</a>
            <a href="<?php echo BASE_URL; ?>?page=webhook">Настройки Webhook</a>
        <?php endif; ?>
    </div>
    <div>
        <?php echo htmlspecialchars($_SESSION['username']); ?>
        <a href="<?php echo BASE_URL; ?>?page=logout" style="color: #ffcccc; margin-left: 15px; text-decoration: none;">Выйти</a>
    </div>
</div>

<div class="card-container">
    <?php if (empty($resultEnvs)): ?>
        <div class="card">
            <div class="env-not-found">Площадки не найдены</div>
        </div>
    <?php endif; ?>
    <?php if (!empty($resultEnvs)): ?>
        <?php foreach ($resultEnvs as $env): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-header-name">Площадка: <?php echo htmlspecialchars($env['name']); ?></div>
                        <div class="card-header-path"><b>Путь:</b> <?php echo htmlspecialchars($env['path']); ?></div>
                    </div>

                    <div>
                        <button
                                class="btn btn-primary"
                                id="fetchBtn"
                                onclick="refreshBranches(<?php echo $env['id']; ?>)"
                        >
                            Обновить ветки с сервера
                        </button>
                    </div>
                </div>

                <?php if (!empty($env['alerts'])): ?>
                    <?php foreach ($env['alerts'] as $alert): ?>
                        <?php
                        $bg = $alert['type'] === 'warning' ? '#fff3cd' : '#f8d7da';
                        $color = $alert['type'] === 'warning' ? '#856404' : '#721c24';
                        $border = $alert['type'] === 'warning' ? '#ffeeba' : '#f5c6cb';
                        ?>
                        <div class="notifications" style="background: <?php echo $bg; ?>; color: <?php echo $color; ?>; border: 1px solid <?php echo $border; ?>;">
                            <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">
                                <?php echo $alert['title']; ?>
                            </div>
                            <div style="font-size: 14px; line-height: 1.4;">
                                <?php echo $alert['message']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="card-body">
                    <?php if (!empty($env['branches'])) :?>
                        <div class="branch-row branch-header">
                            <div>Ветка</div>
                            <div>Последние коммиты</div>
                            <div>Текущая ветка</div>
                            <div>Действия</div>
                        </div>
                        <?php foreach ($env['branches'] as $branch) : ?>
                            <div class="branch-row">
                                <div class="branch-name"><?php echo $branch['name']; ?></div>
                                <div class="commits">
                                    <?php if (!empty($branch['commits']) && is_array($branch['commits'])) :?>
                                        <?php foreach ($branch['commits'] as $commit) : ?>
                                            <?php
                                            $hash = $commit['hash'];
                                            $author = $commit['author'];
                                            $time = $commit['time'];
                                            $msg = $commit['msg'];
                                            ?>
                                            <div class="commit-item">
                                                <?php if ($hash): ?>
                                                    <div class="commit-header">
                                                        <span class="commit-hash"><?php echo $hash; ?></span>
                                                        <span class="commit-author"><?php echo htmlspecialchars($author); ?></span>
                                                        <span class="commit-time"><?php echo $time; ?></span>
                                                    </div>
                                                    <div class="commit-msg" title="<?php echo htmlspecialchars($msg); ?>">
                                                        <?php echo htmlspecialchars($msg); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="commit-msg"><?php echo htmlspecialchars($msg); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach ; ?>
                                    <?php else: ?>
                                        <div style="color: #999; font-size: 13px; font-style: italic;">Нет коммитов</div>
                                    <?php endif ;?>
                                </div>
                                <div class="is-current-branch <?php echo $branch['isCurrentBranch'] ? 'current' : ''; ?>"><?php echo $branch['isCurrentBranch'] ? 'Да' : 'Нет'; ?></div>
                                <div>
                                    <button
                                            class="btn btn-primary"
                                            type="button"
                                            onclick="openSettingsModal(<?php echo $env['id']; ?>, '<?php echo $branch['name']; ?>')"
                                    >
                                        Развернуть
                                    </button>
                                </div>
                            </div>
                        <?php endforeach ; ?>
                    <?php endif ;?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="deployModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div id="modalTitle" class="modal-header">Переключение ветки...</div>

        <div id="deployLoader" class="loader"></div>

        <div id="deployLog" class="terminal-log" style="display: none;"></div>

        <button id="closeModalBtn" class="btn btn-close" style="display: none;" onclick="closeAndRefresh()">
            Закрыть и обновить данные
        </button>
    </div>
</div>

<div id="settingsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content settings-modal-content">
        <div class="modal-header">Подготовка к деплою</div>
        <p>Ветка: <strong id="settingsBranchName" style="color: #007bff;"></strong></p>

        <div class="settings-modal-input">
            <label>
                <input type="checkbox" id="forceBuildCheckbox">
                <span style="font-weight: bold;">Принудительная сборка</span><br>
                <small style="color: #666; margin-left: 20px;">Выполнить build-команду, проигнорировав детектор изменений</small>
            </label>

            <label style="color: #dc3545;">
                <input type="checkbox" id="hardResetCheckbox">
                <span style="font-weight: bold;">Жесткий сброс (Hard Reset)</span><br>
                <small style="color: #dc3545; margin-left: 20px;">Уничтожит все ручные изменения файлов на сервере</small>
            </label>
        </div>

        <div class="settings-modal-footer">
            <button onclick="closeSettingsModal()" class="btn btn-cancel">Отмена</button>
            <button onclick="startDeployment()" class="btn btn-primary">Начать деплой</button>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>assets/js/script.js"></script>

</body>
</html>