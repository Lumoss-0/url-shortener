<?php
// Файл: includes/functions.php
// Функции валидации, генерации кодов, работы со ссылками и логами

require_once __DIR__ . '/../config/config.php';

/**
 * 1. Проверка корректности длинного URL
 */
function validateUrl(string $url): ?string
{
    if ($url === '') {
        return 'Пожалуйста, введите ссылку для сокращения.';
    }

    if (strlen($url) > MAX_URL_LENGTH) {
        return 'Ссылка слишком длинная (максимум ' . MAX_URL_LENGTH . ' символов).';
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
    if ($length < MIN_CUSTOM_CODE_LENGTH or $length > MAX_CUSTOM_CODE_LENGTH) {
        return 'Псевдоним должен содержать от ' . MIN_CUSTOM_CODE_LENGTH . ' до ' . MAX_CUSTOM_CODE_LENGTH . ' символов.';
    }

    if (in_array(strtolower($code), RESERVED_SHORT_CODES, true)) {
        return 'Этот псевдоним зарезервирован системой. Выберите другой.';
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
        return 'Псевдоним может содержать только латинские буквы, цифры, дефис (-) и нижнее подчёркивание (_).';
    }

    return null;
}

/**
 * 3. Проверка: занят ли уже короткий код в базе данных
 * Параметр $excludeId нужен при редактировании ссылки в админке
 */
function isCodeTaken(PDO $pdo, string $code, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM links WHERE short_code = ? AND id != ? LIMIT 1');
        $stmt->execute([$code, $excludeId]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM links WHERE short_code = ? LIMIT 1');
        $stmt->execute([$code]);
    }

    return (bool)$stmt->fetch();
}

/**
 * 4. Генерация уникального кода с помощью встроенной функции uniqid()
 */
function generateUniqueCode(PDO $pdo, int $length = SHORT_CODE_LENGTH): string
{
    do {
        $code = substr(uniqid(), -$length);
    } while (isCodeTaken($pdo, $code));

    return $code;
}

/**
 * 5. Сохранение новой ссылки с привязкой к токену владельца и IP
 */
function saveLink(
    PDO $pdo,
    string $originalUrl,
    string $shortCode,
    string $ownerToken,
    string $creatorIp
): bool {
    $stmt = $pdo->prepare(
        'INSERT INTO links (original_url, short_code, owner_token, creator_ip) VALUES (?, ?, ?, ?)'
    );
    return $stmt->execute([$originalUrl, $shortCode, $ownerToken, $creatorIp]);
}

/**
 * 6. Поиск активной ссылки по короткому коду (отключённые админом ссылки не возвращаются)
 */
function getActiveOriginalUrl(PDO $pdo, string $shortCode): ?string
{
    $stmt = $pdo->prepare('SELECT original_url FROM links WHERE short_code = ? AND is_active = 1 LIMIT 1');
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
 * 8. Получение последних ссылок ТОЛЬКО текущего владельца (по его cookie-токену)
 */
function getRecentLinksByOwner(PDO $pdo, string $ownerToken, int $limit = RECENT_LINKS_LIMIT): array
{
    $stmt = $pdo->prepare(
        'SELECT original_url, short_code, clicks, is_active, created_at
         FROM links
         WHERE owner_token = ?
         ORDER BY id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $ownerToken, PDO::PARAM_STR);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * 9. Запись события в журнал логов (таблица logs)
 */
function writeLog(PDO $pdo, string $eventType, string $message, string $ipAddress): void
{
    $stmt = $pdo->prepare('INSERT INTO logs (event_type, message, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$eventType, $message, $ipAddress]);
}

/**
 * 10. Перевод даты/времени из UTC (как хранит SQLite) в часовой пояс из APP_TIMEZONE
 */
function formatDateTime(string $utcDateTime): string
{
    if ($utcDateTime === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone(APP_TIMEZONE))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $utcDateTime;
    }
}