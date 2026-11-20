<?php
// Файл: redirect.php
// Обработка перехода по короткой ссылке, подсчёт кликов и логирование

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

applySecurityHeaders();

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pdo = getDbConnection();
$originalUrl = getActiveOriginalUrl($pdo, $code);

if ($originalUrl !== null) {
    incrementClicks($pdo, $code);
    writeLog($pdo, 'redirect', 'Переход по /' . $code, getClientIp());

    header('Location: ' . $originalUrl, true, 302);
    exit;
}

writeLog($pdo, 'not_found', 'Запрос несуществующей или отключённой ссылки /' . $code, getClientIp());
http_response_code(404);
require __DIR__ . '/404.php';
exit;