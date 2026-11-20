<?php
// Файл: includes/security.php
// Базовая безопасность: заголовки, сессии, CSRF-токены, cookie владельца и защита входа

require_once __DIR__ . '/../config/config.php';

/**
 * Короткая функция экранирования текста для защиты от XSS-атак
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Проверяет, открыт ли сайт по HTTPS (учитывает работу за прокси на Render.com)
 */
function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }

    return false;
}

/**
 * Возвращает базовый адрес сайта (например, http://localhost:8000 или https://...onrender.com)
 */
function getBaseUrl(): string
{
    $protocol = isHttpsRequest() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

    return $protocol . '://' . $host;
}

/**
 * Отправляет базовые HTTP-заголовки безопасности
 */
function applySecurityHeaders(): void
{
    // Запрещаем встраивать наш сайт в чужие <iframe> (защита от Clickjacking)
    header('X-Frame-Options: DENY');
    // Запрещаем браузеру угадывать тип файла (защита от MIME-sniffing)
    header('X-Content-Type-Options: nosniff');
    // Ограничиваем передачу адреса источника при переходе на другие сайты
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Запускает сессию с безопасными настройками cookie
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,      // JavaScript не сможет украсть cookie сессии
        'samesite' => 'Lax',     // Защита от межсайтовых атак
    ]);

    session_start();
}

/**
 * Получает IP-адрес пользователя (для записи в логи)
 */
function getClientIp(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        // Если запрос прошёл через прокси (как на Render), берём первый IP из списка
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
}

/**
 * Генерирует или возвращает текущий CSRF-токен для защиты форм
 */
function getCsrfToken(): string
{
    startSecureSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Проверяет CSRF-токен из POST-запроса (сравнение устойчиво к атакам по времени)
 */
function verifyCsrfToken(?string $token): bool
{
    startSecureSession();

    if (empty($_SESSION['csrf_token']) or $token === null or $token === '') {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Возвращает личный секретный токен браузера из cookie (или создаёт новый),
 * чтобы человек видел в списке «Мои недавние ссылки» только свои ссылки
 */
function getOrCreateOwnerToken(): string
{
    $existing = $_COOKIE[OWNER_COOKIE_NAME] ?? '';

    // Проверяем, что в cookie лежит именно наш 32-символьный hex-токен
    if (preg_match('/^[a-f0-9]{32}$/', $existing)) {
        return $existing;
    }

    $newToken = bin2hex(random_bytes(16));

    setcookie(OWNER_COOKIE_NAME, $newToken, [
        'expires'  => time() + OWNER_COOKIE_LIFETIME,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[OWNER_COOKIE_NAME] = $newToken;

    return $newToken;
}

/**
 * Проверяет, вошёл ли администратор в систему
 */
function isAdminLoggedIn(): bool
{
    startSecureSession();
    return !empty($_SESSION['admin_id']);
}

/**
 * Если админ не вошёл — перенаправляет на страницу входа /admin/login
 */
function requireAdminAuth(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login');
        exit;
    }
}

/**
 * Проверка блокировки входа после слишком большого числа неверных паролей
 */
function getLoginBlockRemainingSeconds(): int
{
    startSecureSession();
    $blockedUntil = (int)($_SESSION['login_blocked_until'] ?? 0);
    $remaining = $blockedUntil - time();

    return $remaining > 0 ? $remaining : 0;
}

/**
 * Регистрирует неудачную попытку входа в админку
 */
function registerFailedLoginAttempt(): void
{
    startSecureSession();
    $attempts = (int)($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_attempts'] = $attempts;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['login_blocked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
        $_SESSION['login_attempts'] = 0;
    }
}

/**
 * Сбрасывает счётчик неудачных попыток после успешного входа
 */
function resetLoginAttempts(): void
{
    startSecureSession();
    unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);
}