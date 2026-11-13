<?php
// Файл: includes/functions.php
// Основные функции для проверки данных, генерации кодов и работы с БД

/**
 * 1. Проверка корректности длинного URL
 * Возвращает текст ошибки или null, если всё хорошо
 */
function validateUrl(string $url): ?string
{
    // Проверяем, не пустая ли строка
    if ($url === '') {
        return 'Пожалуйста, введите ссылку для сокращения.';
    }

    // Ограничиваем длину ссылки (максимум 2048 символов, как в таблице БД)
    if (strlen($url) > 2048) {
        return 'Ссылка слишком длинная (максимум 2048 символов).';
    }

    // Встроенная в PHP проверка формата URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return 'Некорректный формат ссылки. Пример правильной ссылки: https://example.com';
    }

    // Разбираем URL на части и проверяем, что протокол строго http или https
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
        return 'Разрешены только ссылки, начинающиеся с http:// или https://';
    }

    return null; // Ошибок нет
}

/**
 * 2. Проверка пользовательского псевдонима
 * Возвращает текст ошибки или null, если всё хорошо
 */
function validateCustomCode(string $code): ?string
{
    // Проверяем длину псевдонима (от 3 до 50 символов)
    $length = strlen($code);
    if ($length < 3 || $length > 50) {
        return 'Псевдоним должен содержать от 3 до 50 символов.';
    }

    // Запрещаем называть ссылку именами системных файлов нашего проекта
    $reservedWords = ['index.php', 'redirect.php', '404.php', 'router.php', 'assets', 'config', 'includes'];
    if (in_array(strtolower($code), $reservedWords, true)) {
        return 'Этот псевдоним зарезервирован системой. Выберите другой.';
    }

    // Проверяем допустимые символы: латинские буквы, цифры, дефис (-) и нижнее подчёркивание (_)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
        return 'Псевдоним может содержать только латинские буквы, цифры, дефис (-) и нижнее подчёркивание (_).';
    }

    return null; // Ошибок нет
}

/**
 * 3. Проверка: занят ли уже короткий код в базе данных
 * Возвращает true (если занят) или false (если свободен)
 */
function isCodeTaken(PDO $pdo, string $code): bool
{
    // Используем подготовленный запрос со знаком вопроса (?) для защиты от SQL-инъекций
    $stmt = $pdo->prepare('SELECT id FROM links WHERE short_code = ? LIMIT 1');
    $stmt->execute([$code]);

    // Если fetch() вернул строку — значит, такой код уже есть в базе
    return (bool)$stmt->fetch();
}

/**
 * 4. Генерация случайного уникального кода (по умолчанию из 6 символов)
 */
function generateUniqueCode(PDO $pdo, int $length = 6): string
{
    do {
        // uniqid() создаёт уникальный код на основе текущего времени в микросекундах.
        // substr(..., -$length) берёт последние 6 самых уникальных символов (чтобы ссылка была короткой).
        // Примечание: если нужен полный 13-символьный код, можно просто написать: $code = uniqid();
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
 * Возвращает строку с URL или null, если ссылка не найдена
 */
function getOriginalUrl(PDO $pdo, string $shortCode): ?string
{
    $stmt = $pdo->prepare('SELECT original_url FROM links WHERE short_code = ? LIMIT 1');
    $stmt->execute([$shortCode]);
    $row = $stmt->fetch();

    return $row ? $row['original_url'] : null;
}