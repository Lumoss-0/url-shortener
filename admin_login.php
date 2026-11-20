<?php
// Файл: admin_login.php
// Страница авторизации администратора (/admin/login)

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/admin_functions.php';

applySecurityHeaders();
startSecureSession();

// Если администратор уже вошёл — сразу перенаправляем в панель
if (isAdminLoggedIn()) {
    header('Location: /admin');
    exit;
}

$pdo = getDbConnection();
$csrfToken = getCsrfToken();
$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $postedToken = $_POST['csrf_token'] ?? null;
    $clientIp = getClientIp();

    $blockedSeconds = getLoginBlockRemainingSeconds();

    if ($blockedSeconds > 0) {
        $error = 'Слишком много неудачных попыток входа. Попробуйте снова через ' . $blockedSeconds . ' сек.';
    } elseif (!verifyCsrfToken(is_string($postedToken) ? $postedToken : null)) {
        $error = 'Ошибка безопасности (неверный CSRF-токен). Обновите страницу и попробуйте снова.';
    } elseif ($username === '' or $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $admin = findAdminByUsername($pdo, $username);

        // Проверяем логин и сверяем пароль с хешем через password_verify()
        if ($admin !== null && password_verify($password, $admin['password_hash'])) {
            resetLoginAttempts();

            // Меняем ID сессии после входа (защита от перехвата сессии)
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            writeLog($pdo, 'admin_login', 'Успешный вход администратора: ' . $admin['username'], $clientIp);

            header('Location: /admin');
            exit;
        }

        // Неудачная попытка входа
        registerFailedLoginAttempt();
        writeLog($pdo, 'admin_login_failed', 'Неудачная попытка входа с логином: ' . $username, $clientIp);

        $newBlockedSeconds = getLoginBlockRemainingSeconds();
        if ($newBlockedSeconds > 0) {
            $error = 'Превышено число попыток. Вход временно заблокирован на ' . $newBlockedSeconds . ' сек.';
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель — URL Shortener</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <main class="container">
        <div class="card">
            <div class="brand-header">
                <div class="logo-badge" title="Панель администратора">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <h1>Админ-панель</h1>
                <p class="subtitle">Вход для управления ссылками и просмотра логов</p>
            </div>

            <?php if ($error !== null): ?>
                <div class="alert alert-error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="form-group">
                    <label for="username">Логин</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Введите логин"
                        value="<?= e($username) ?>"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Введите пароль"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary">Войти в панель</button>
            </form>

            <div class="text-center" style="margin-top: 18px;">
                <a href="/" class="admin-back-link">&larr; Вернуться к сокращателю ссылок</a>
            </div>
        </div>
    </main>
</body>
</html>