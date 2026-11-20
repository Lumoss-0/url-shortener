<?php
// Файл: config/config.php
// Основные константы и настройки приложения

// Путь к файлу SQLite: если задана переменная среды DB_PATH (как в Docker), берём её,
// иначе сохраняем database.sqlite в корне проекта
$envDbPath = getenv('DB_PATH');
define(
    'DB_PATH',
    ($envDbPath !== false && $envDbPath !== '')
        ? $envDbPath
        : dirname(__DIR__) . '/database.sqlite'
);

// Ограничения для ссылок и коротких кодов
define('MAX_URL_LENGTH', 2048);
define('SHORT_CODE_LENGTH', 6);
define('MIN_CUSTOM_CODE_LENGTH', 3);
define('MAX_CUSTOM_CODE_LENGTH', 50);
define('RECENT_LINKS_LIMIT', 5);

// Настройки cookie для приватного списка «Мои недавние ссылки» (хранится 30 дней)
define('OWNER_COOKIE_NAME', 'shortener_owner');
define('OWNER_COOKIE_LIFETIME', 60 * 60 * 24 * 30);

// Настройки защиты от подбора пароля в админке (5 попыток, затем пауза 5 минут)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 300);

// Данные администратора по умолчанию (используются только при первом создании таблицы admins).
// Сам пароль хранится в базе в зашифрованном виде через password_hash().
$envAdminLogin = getenv('ADMIN_LOGIN');
$envAdminPass  = getenv('ADMIN_PASSWORD');
define('DEFAULT_ADMIN_LOGIN', ($envAdminLogin !== false && $envAdminLogin !== '') ? $envAdminLogin : 'admin');
define('DEFAULT_ADMIN_PASSWORD', ($envAdminPass !== false && $envAdminPass !== '') ? $envAdminPass : 'admin123');

// Системные пути, которые нельзя использовать в качестве короткого псевдонима
define('RESERVED_SHORT_CODES', [
    'index.php',
    'redirect.php',
    '404.php',
    'router.php',
    'admin',
    'admin.php',
    'admin_login.php',
    'database.sqlite',
    'database.sql',
    'assets',
    'config',
    'includes',
]);

// Часовой пояс для отображения даты и времени (UTC+5)
$envTimezone = getenv('APP_TIMEZONE');
define('APP_TIMEZONE', ($envTimezone !== false && $envTimezone !== '') ? $envTimezone : 'Asia/Yekaterinburg');