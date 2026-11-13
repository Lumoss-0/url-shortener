<?php
// Файл: index.php
// Главная страница: отображение формы и обработка создания короткой ссылки

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = null;          // Текст ошибки (если что-то пойдёт не так)
$shortUrl = null;       // Готовая короткая ссылка для вывода пользователю
$originalUrl = '';      // Переменная для сохранения введённого URL в поле формы
$customCode = '';       // Переменная для сохранения введённого псевдонима
$finalCode = '';        // Короткий код, который будет сохранён в базу

// Проверяем, отправил ли пользователь форму (методом POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы и убираем случайные пробелы по краям через trim()
    $originalUrl = trim($_POST['url'] ?? '');
    $customCode  = trim($_POST['custom_code'] ?? '');

    // 1. Проверяем корректность длинной ссылки
    $error = validateUrl($originalUrl);

    // 2. Если ссылка корректна, разбираемся с коротким кодом
    if ($error === null) {
        if ($customCode !== '') {
            // Пользователь указал свой псевдоним — проверяем его формат
            $error = validateCustomCode($customCode);

            // Если формат правильный, проверяем, не занят ли этот псевдоним в базе
            if ($error === null && isCodeTaken($pdo, $customCode)) {$error = 'Этот псевдоним уже занят. Пожалуйста, придумайте другой.';
            }

            $finalCode =$customCode;
        } else {
            // Пользователь не указал псевдоним — генерируем случайный уникальный код
            $finalCode = generateUniqueCode($pdo, 6);
        }
    }

    // 3. Если никаких ошибок нет — сохраняем ссылку в базу данных
    if ($error === null) {
        try {
            saveLink($pdo, $originalUrl,$finalCode);

            // Определяем текущий адрес сайта (например, http://localhost:8000)
            $protocol = (!empty($_SERVER['HTTPS']) &&$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host =$_SERVER['HTTP_HOST'] ?? 'localhost:8000';

            // Собираем готовую короткую ссылку
            $shortUrl =$protocol . '://' . $host . '/' .$finalCode;

            // Очищаем поля формы после успешного создания
            $originalUrl = '';$customCode = '';
        } catch (PDOException $e) {$error = 'Произошла ошибка при сохранении ссылки. Попробуйте ещё раз.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сокращатель ссылок — URL Shortener</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- Фоновые анимированные сферы -->
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <main class="container">
        <div class="card">
            <div class="brand-header">
                <div class="logo-badge" title="URL Shortener">
                    <!-- Иконка звена ссылки -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                </div>
                <h1>Сокращатель ссылок</h1>
                <p class="subtitle">Превратите длинный веб-адрес в короткую и удобную ссылку за секунду</p>
            </div>

            <!-- Вывод блока с ошибкой, если она возникла -->
            <?php if ($error !== null): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Вывод готовой короткой ссылки после успешного создания -->
            <?php if ($shortUrl !== null): ?>
                <div class="alert alert-success">
                    <p class="result-label">&#10024; Ваша короткая ссылка готова:</p>
                    <div class="result-box">
                        <a href="<?= htmlspecialchars($shortUrl, ENT_QUOTES, 'UTF-8') ?>" 
                           target="_blank" 
                           id="short-url-link" 
                           class="short-link">
                            <?= htmlspecialchars($shortUrl, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <button type="button" id="copy-btn" class="btn-secondary" data-url="<?= htmlspecialchars($shortUrl, ENT_QUOTES, 'UTF-8') ?>">
                            Копировать
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Форма создания короткой ссылки -->
            <form method="POST" action="/" novalidate>
                <div class="form-group">
                    <label for="url">Длинная ссылка <span class="required">*</span></label>
                    <input 
                        type="url" 
                        id="url" 
                        name="url" 
                        placeholder="https://example.com/very/long/address" 
                        value="<?= htmlspecialchars($originalUrl, ENT_QUOTES, 'UTF-8') ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="custom_code">Свой псевдоним <span class="optional">(необязательно)</span></label>
                    <input 
                        type="text" 
                        id="custom_code" 
                        name="custom_code" 
                        placeholder="Например: my-project_2026" 
                        value="<?= htmlspecialchars($customCode, ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="50"
                    >
                    <small class="hint">Разрешены латинские буквы, цифры, дефис (-) и подчёркивание (_), от 3 до 50 символов.</small>
                </div>

                <button type="submit" class="btn-primary">
                    <span class="shrink-arrow arrow-left">&rarr;</span>
                    <span>Сократить ссылку</span>
                    <span class="shrink-arrow arrow-right">&larr;</span>
                </button>
            </form>
        </div>
    </main>

    <script src="/assets/script.js"></script>
</body>
</html>