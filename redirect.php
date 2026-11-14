<?php
// Файл: redirect.php
// Обработка перехода по короткой ссылке и подсчёт кликов

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$originalUrl = getOriginalUrl($pdo, $code);

if ($originalUrl !== null) {
    // Увеличиваем счётчик переходов по этой ссылке на +1
    incrementClicks($pdo, $code);

    // Перенаправляем пользователя на исходный адрес
    header('Location: ' . $originalUrl, true, 302);
    exit;
}

http_response_code(404);
require __DIR__ . '/404.php';
exit;