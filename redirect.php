<?php
// Файл: redirect.php
// Обработка перехода по короткой ссылке

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Получаем короткий код из запроса
$code = trim($_GET['code'] ?? '');

// Если код пустой — показываем страницу 404
if ($code === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Ищем исходную длинную ссылку в базе данных
$originalUrl = getOriginalUrl($pdo,$code);

if ($originalUrl !== null) {
    // Если ссылка найдена — перенаправляем пользователя (код 302 — временное перенаправление)
    header('Location: ' . $originalUrl, true, 302);
    exit;
}

// Если ссылка в базе не найдена — отправляем статус 404 и показываем страницу ошибки
http_response_code(404);
require __DIR__ . '/404.php';
exit;