<?php
// Файл: config/db.php
// Подключение к SQLite через PDO, обёрнутое в функции

require_once __DIR__ . '/config.php';

/**
 * Возвращает единое подключение PDO к базе данных SQLite
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // Если папки для базы ещё нет (например, /data в Docker), пробуем создать её
    $dbDir = dirname(DB_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }

    $dsn = 'sqlite:' . DB_PATH;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, null, null, $options);
        initDatabase($pdo);
    } catch (PDOException $e) {
        http_response_code(500);
        die('Ошибка подключения к базе данных SQLite: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }

    return $pdo;
}

/**
 * Создаёт нужные таблицы, обновляет старую таблицу links и создаёт админа по умолчанию
 */
function initDatabase(PDO $pdo): void
{
    // 1. Таблица ссылок
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            original_url TEXT NOT NULL,
            short_code TEXT NOT NULL UNIQUE,
            clicks INTEGER NOT NULL DEFAULT 0,
            owner_token TEXT NOT NULL DEFAULT '',
            creator_ip TEXT NOT NULL DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Проверяем, есть ли новые колонки в уже существующем файле database.sqlite
    $existingColumns = [];
    foreach ($pdo->query("PRAGMA table_info(links)")->fetchAll() as $col) {
        $existingColumns[] = $col['name'];
    }

    $migrations = [
        'clicks'      => "ALTER TABLE links ADD COLUMN clicks INTEGER NOT NULL DEFAULT 0",
        'owner_token' => "ALTER TABLE links ADD COLUMN owner_token TEXT NOT NULL DEFAULT ''",
        'creator_ip'  => "ALTER TABLE links ADD COLUMN creator_ip TEXT NOT NULL DEFAULT ''",
        'is_active'   => "ALTER TABLE links ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1",
    ];

    foreach ($migrations as $columnName => $sql) {
        if (!in_array($columnName, $existingColumns, true)) {
            $pdo->exec($sql);
        }
    }

    // 2. Таблица логов (журнал событий для админки)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type TEXT NOT NULL,
            message TEXT NOT NULL,
            ip_address TEXT NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. Таблица администраторов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Если в таблице admins ещё никого нет — создаём первого админа с безопасным хешем пароля
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($adminCount === 0) {
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
        $stmt->execute([
            DEFAULT_ADMIN_LOGIN,
            password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
        ]);
    }
}