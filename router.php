<?php
// Файл: router.php
// Маршрутизатор для встроенного веб-сервера PHP (php -S localhost:8000 router.php)

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$fullPath = __DIR__ . $path;

// Запрещаем прямой доступ к файлам базы данных и конфигурации через браузер
if (preg_match('/\.(sqlite|sql|md)$/i', $path)) {
    http_response_code(403);
    exit('Доступ запрещён');
}

// 1. Если запрашивается существующий файл (например, style.css или script.js) — отдаём его как есть
if ($path !== '/' && file_exists($fullPath) && !is_dir($fullPath)) {
    return false;
}

// 2. Если открыта главная страница — загружаем index.php
if ($path === '/' or $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// 3. Во всех остальных случаях считаем путь коротким кодом
$_GET['code'] = ltrim($path, '/');
require __DIR__ . '/redirect.php';
return true;