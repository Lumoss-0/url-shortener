<?php
// Файл: includes/functions.php
// Основные функции для проверки данных, генерации кодов и работы с БД

/**
 * 1. Проверка корректности длинного URL
 */
function validateUrl(string $url): ?string
{
    if ($url === '') {
        return 'Пожалуйста, введите ссылку для сокращения.';
    }

    if (strlen($url) > 2048) {
        return 'Ссылка слишком длинная (максимум 2048 символов).';
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return 'Некорректный формат ссылки. Пример правильной ссылки: https://example.com';
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
        return 'Разрешены только ссылки, начинающиеся с http:// или https://';
    }

    return null;
}

/**
 * 2. Проверка пользовательского псевдонима
 */
function validateCustomCode(string $code): ?string
{
    $length = strlen($code);
    if ($length < 3 or $length > 50) {
        return 'Псевдоним должен содержать от 3 до 50 символов.';
    }

    $reservedWords = [
        'index.php', 'redirect.php', '404.php', 'router.php',
        'database.sqlite', 'database.sql', 'assets', 'config', 'includes'
    ];
    if (in_array(strtolower($code), $reservedWords, true)) {
        return 'Этот псевдоним зарезервирован системой. Выберите другой.';
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
        return 'Псевдоним может содержать только латинские буквы, цифры, дефис (-) и нижнее подчёркивание (_).';
    }

    return null;
}

/**
 * 3. Проверка: занят ли уже короткий код в базе данных
 */
function isCodeTaken(PDO $pdo, string $code): bool
{
    $stmt = $pdo->prepare('SELECT id FROM links WHERE short_code = ? LIMIT 1');
    $stmt->execute([$code]);

    return (bool)$stmt->fetch();
}

/**
 * 4. Генерация уникального кода с помощью встроенной функции uniqid()
 */
function generateUniqueCode(PDO $pdo, int $length = 6): string
{
    do {
        $code = substr(uniqid(), -$length);
    } while (isCodeTaken($pdo, $code));

    return $code;
}

/**
 * 5. Сохранение новой ссылки в базу данных
 */
function saveLink(PDO $pdo, string $originalUrl, string $shortCode): bool
{
    $stmt = $pdo->prepare('INSERT INTO links (original_url, short_code) VALUES (?, ?)');
    return $stmt->execute([$originalUrl, $shortCode]);
}

/**
 * 6. Поиск исходной (длинной) ссылки по короткому коду
 */
function getOriginalUrl(PDO $pdo, string $shortCode): ?string
{
    $stmt = $pdo->prepare('SELECT original_url FROM links WHERE short_code = ? LIMIT 1');
    $stmt->execute([$shortCode]);
    $row = $stmt->fetch();

    return $row ? $row['original_url'] : null;
}

/**
 * 7. Увеличение счётчика переходов (кликов) на +1
 */
function incrementClicks(PDO $pdo, string $shortCode): void
{
    $stmt = $pdo->prepare('UPDATE links SET clicks = clicks + 1 WHERE short_code = ?');
    $stmt->execute([$shortCode]);
}

/**
 * 8. Получение последних созданных ссылок для таблицы истории
 */
function getRecentLinks(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->prepare('SELECT original_url, short_code, clicks, created_at FROM links ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}