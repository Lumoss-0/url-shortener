<?php
// Файл: config/db.php
// Подключение к базе данных SQLite через PDO

$dbFile = __DIR__ . '/../database.sqlite';
$dsn = 'sqlite:' . $dbFile;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, null, null, $options);

    // 1. Создаём таблицу links (с полем clicks), если база создаётся с нуля
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            original_url TEXT NOT NULL,
            short_code TEXT NOT NULL UNIQUE,
            clicks INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Если таблица была создана раньше без колонки clicks — автоматически добавляем её
    $columns = $pdo->query("PRAGMA table_info(links)")->fetchAll();
    $hasClicks = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'clicks') {
            $hasClicks = true;
            break;
        }
    }
    if (!$hasClicks) {
        $pdo->exec("ALTER TABLE links ADD COLUMN clicks INTEGER NOT NULL DEFAULT 0");
    }
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных SQLite: ' . htmlspecialchars($e->getMessage()));
}