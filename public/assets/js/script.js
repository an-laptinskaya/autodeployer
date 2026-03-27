async function login(event) {
    event.preventDefault();

    const btn = event.target.querySelector('button[type="submit"]');
    btn.innerText = 'Вход...';
    btn.disabled = true;

    const alertBox = document.getElementById('addAlert');

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    try {
        const response = await fetch(BASE_URL + '?api=login', {
            method: 'POST',
            body: JSON.stringify({
                username: username,
                password: password,
            }),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            window.location.href = BASE_URL + "?page=branches";
        } else {
            alertBox.innerText = result.error;
            alertBox.style.display = 'block';
            btn.innerText = 'Войти';
            btn.disabled = false;
        }
    } catch (error) {
        alertBox.innerText = 'Сетевая ошибка: ' + error.message;
        alertBox.style.display = 'block';
        btn.innerText = 'Войти';
        btn.disabled = false;
    }
}

async function addUser(event) {
    event.preventDefault();

    const btn = document.getElementById('addBtn');
    const alertBox = document.getElementById('addAlert');

    const login = document.getElementById('newLogin').value;
    const password = document.getElementById('newPassword').value;
    const isAdmin = document.getElementById('newIsAdmin').checked;

    btn.innerText = 'Загрузка...';
    btn.disabled = true;

    try {
        const response = await fetch(BASE_URL + '?api=add_user', {
            method: 'POST',
            body: JSON.stringify({
                login: login,
                password: password,
                is_admin: isAdmin
            }),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alertBox.className = 'alert alert-error';
            alertBox.innerText = result.error;
            alertBox.style.display = 'block';
            btn.innerText = 'Добавить';
            btn.disabled = false;
        }
    } catch (error) {
        alertBox.className = 'alert alert-error';
        alertBox.innerText = 'Сетевая ошибка: ' + error.message;
        alertBox.style.display = 'block';
        btn.innerText = 'Добавить';
        btn.disabled = false;
    }
}

async function deleteUser(userId, userLogin) {
    if (!confirm(`Вы уверены, что хотите удалить пользователя ${userLogin}?`)) {
        return;
    }

    try {
        const response = await fetch(BASE_URL + '?api=delete_user', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId
            }),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert('Ошибка: ' + result.error);
        }
    } catch (error) {
        alert('Сетевая ошибка: ' + error.message);
    }
}

async function saveEnv(event, action) {
    event.preventDefault();

    const actionRoute = action === 'add' ? 'add_environment' : 'edit_environment';
    const prefix = action === 'add' ? 'add' : 'edit';
    const btn = document.getElementById(prefix + 'Btn');
    const alertBox = document.getElementById(prefix + 'Alert');
    const originalText = btn.innerText;

    const data = {
        name: document.getElementById(prefix + 'Name').value,
        path: document.getElementById(prefix + 'Path').value,
        target_branch: document.getElementById(prefix + 'Branch').value,
        build_command: document.getElementById(prefix + 'Command').value,
        build_triggers: document.getElementById(prefix + 'Triggers').value
    };

    if (action === 'edit') data.id = document.getElementById('editId').value;

    btn.innerText = 'Загрузка...';
    btn.disabled = true;

    try {
        const response = await fetch(BASE_URL + '?api=' + actionRoute, {
            method: 'POST',
            body: JSON.stringify(data),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alertBox.className = 'alert alert-error';
            alertBox.innerText = result.error;
            alertBox.style.display = 'block';
            btn.innerText = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        alertBox.className = 'alert alert-error';
        alertBox.innerText = 'Сетевая ошибка: ' + error.message;
        alertBox.style.display = 'block';
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

async function deleteEnv(id, name) {
    if (!confirm(`Удалить площадку "${name}" из базы данных? (Папка на сервере останется нетронутой)`)) return;

    try {
        const response = await fetch(BASE_URL + '?api=delete_environment', {
            method: 'POST',
            body: JSON.stringify({ id: id }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        if (result.success) window.location.reload();
        else alert('Ошибка: ' + result.error);
    } catch (error) {
        alert('Сетевая ошибка: ' + error.message);
    }
}

function openEditModal(env) {
    document.getElementById('editId').value = env.id;
    document.getElementById('editName').value = env.name;
    document.getElementById('editPath').value = env.path;
    document.getElementById('editBranch').value = env.target_branch;
    document.getElementById('editCommand').value = env.build_command || '';
    document.getElementById('editTriggers').value = env.build_triggers || '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

let currentDeployEnvId = null;
let currentDeployBranch = null;

function openSettingsModal(envId, branchName) {
    currentDeployEnvId = envId;
    currentDeployBranch = branchName;

    document.getElementById('settingsBranchName').innerText = branchName;

    document.getElementById('forceBuildCheckbox').checked = false;
    document.getElementById('hardResetCheckbox').checked = false;

    document.getElementById('settingsModal').style.display = 'flex';
}

function closeSettingsModal() {
    document.getElementById('settingsModal').style.display = 'none';
}

async function startDeployment() {
    const isForceBuild = document.getElementById('forceBuildCheckbox').checked;
    const isHardReset = document.getElementById('hardResetCheckbox').checked;
    const gitStrategy = isHardReset ? 'reset' : 'commit';

    closeSettingsModal();

    const modal = document.getElementById('deployModal');
    const title = document.getElementById('modalTitle');
    const loader = document.getElementById('deployLoader');
    const logBox = document.getElementById('deployLog');
    const closeBtn = document.getElementById('closeModalBtn');

    modal.style.display = 'flex';
    title.innerText = `Деплой ветки: ${currentDeployBranch}...`;
    title.style.color = '#333';
    loader.style.display = 'block';
    logBox.style.display = 'none';
    closeBtn.style.display = 'none';

    try {
        const response = await fetch(BASE_URL + '?api=change_branch', {
            method: 'POST',
            body: JSON.stringify({
                envId: currentDeployEnvId,
                branchName: currentDeployBranch,
                strategy: gitStrategy,
                forceBuild: isForceBuild
            }),
            headers: { 'Content-Type': 'application/json' }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        loader.style.display = 'none';
        logBox.style.display = 'block';
        closeBtn.style.display = 'block';
        logBox.innerText = result.log || 'Нет логов от сервера.';

        if (result.success) {
            title.innerText = `Ветка ${currentDeployBranch} успешно развернута!`;
            title.style.color = '#28a745';
        } else {
            title.innerText = `Ошибка деплоя ветки ${currentDeployBranch}`;
            title.style.color = '#dc3545';
        }

    } catch (error) {
        loader.style.display = 'none';
        logBox.style.display = 'block';
        closeBtn.style.display = 'block';
        title.innerText = `Системная ошибка`;
        title.style.color = '#dc3545';
        logBox.innerText = `Произошла ошибка при выполнении запроса:\n${error.message}`;
    }
}

function closeAndRefresh() {
    document.getElementById('deployModal').style.display = 'none';
    window.location.reload();
}

async function refreshBranches(envId) {
    const btn = document.getElementById('fetchBtn');
    const originalText = btn.innerText;

    btn.innerText = 'Загрузка...';
    btn.disabled = true;
    btn.style.opacity = '0.7';

    try {
        const response = await fetch(BASE_URL + '?api=fetch_branches', {
            method: 'POST',
            body: JSON.stringify({ envId: envId }),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert('Ошибка при обновлении веток: ' + (result.error || 'Неизвестная ошибка'));
            btn.innerText = originalText;
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    } catch (error) {
        alert('Сетевая ошибка: ' + error.message);
        btn.innerText = originalText;
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function generateToken() {
    if (!confirm("Вы уверены, что хотите сгенерировать НОВЫЙ токен?\n\nЭто приведет к удалению старого токена из базы. Если у вас уже настроены вебхуки в репозиториях, они перестанут работать!")) {
        return;
    }

    const btn = document.getElementById('generateBtn');
    const resultBox = document.getElementById('resultBox');

    btn.disabled = true;
    btn.innerText = 'Генерация...';
    resultBox.style.display = 'none';

    try {
        const response = await fetch(BASE_URL + '?api=generate_webhook_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            resultBox.className = 'alert alert-success';
            resultBox.innerHTML = `
                    <strong>Успешно! Новый токен сгенерирован.</strong><br>
                    Скопируйте его прямо сейчас. Из соображений безопасности <u>он больше никогда не будет показан</u>.
                    <div class="token-box">${result.token}</div>
                `;
            resultBox.style.display = 'block';
        } else {
            resultBox.className = 'alert alert-error';
            resultBox.innerText = 'Ошибка: ' + result.error;
            resultBox.style.display = 'block';
        }
    } catch (error) {
        resultBox.className = 'alert alert-error';
        resultBox.innerText = 'Сетевая ошибка: ' + error.message;
        resultBox.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerText = 'Сгенерировать новый токен';
    }
}