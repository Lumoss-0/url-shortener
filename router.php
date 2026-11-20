<?php
// Файл: router.php
// Маршрутизатор для встроенного веб-сервера PHP (php -S localhost:8000 router.php)

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$fullPath = __DIR__ . $path;

// Запрещаем прямой доступ к служебным папкам и файлам через браузер
if (
    preg_match('#^/(config|includes)(/|$)#i', $path)
    or preg_match('/\.(sqlite|sql|md|yaml|yml)$/i', $path)
    or stripos($path, '/Dockerfile') === 0
) {
    http_response_code(403);
    exit('Доступ запрещён');
}

// 1. Если запрашивается существующий статический файл (style.css, script.js) — отдаём его
if ($path !== '/' && file_exists($fullPath) && !is_dir($fullPath)) {
    return false;
}

// 2. Главная страница
if ($path === '/' or $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// 3. Страница входа в админ-панель
if ($path === '/admin/login' or $path === '/admin/login/') {
    require __DIR__ . '/admin_login.php';
    return true;
}

// 4. Сама админ-панель
if ($path === '/admin' or $path === '/admin/') {
    require __DIR__ . '/admin.php';
    return true;
}

// 5. Во всех остальных случаях считаем путь коротким кодом
$_GET['code'] = ltrim($path, '/');
require __DIR__ . '/redirect.php';
return true;