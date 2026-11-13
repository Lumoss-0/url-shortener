<?php
// Файл: config/db.php
// Подключение к базе данных SQLite через PDO

// В Docker база хранится в постоянном томе; локально — в корне проекта.
$dbFile = getenv('DB_PATH') ?: __DIR__ . '/../database.sqlite';

// Формируем строку подключения (DSN) для SQLite
$dsn = 'sqlite:' . $dbFile;

$options = [
    // При ошибках БД выбрасывать исключения
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Возвращать данные в виде ассоциативного массива
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Отключаем эмуляцию подготовленных запросов
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Создаём подключение к файлу базы данных SQLite
    $pdo = new PDO($dsn, null, null, $options);

    // Автоматически создаём таблицу links, если база только что создана
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            original_url TEXT NOT NULL,
            short_code TEXT NOT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных SQLite: ' . htmlspecialchars($e->getMessage()));
}