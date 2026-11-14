<?php
// Файл: index.php
// Главная страница: форма, QR-код и таблица последних ссылок с аналитикой кликов

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = null;
$shortUrl = null;
$originalUrl = '';
$customCode = '';
$finalCode = '';

// Определяем базовый адрес сайта (корректно работает и локально, и на Render через HTTPS)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    or (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$protocol = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$baseUrl = $protocol . '://' . $host;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $originalUrl = trim($_POST['url'] ?? '');
    $customCode  = trim($_POST['custom_code'] ?? '');

    $error = validateUrl($originalUrl);

    if ($error === null) {
        if ($customCode !== '') {
            $error = validateCustomCode($customCode);

            if ($error === null && isCodeTaken($pdo, $customCode)) {
                $error = 'Этот псевдоним уже занят. Пожалуйста, придумайте другой.';
            }

            $finalCode = $customCode;
        } else {
            $finalCode = generateUniqueCode($pdo, 6);
        }
    }

    if ($error === null) {
        try {
            saveLink($pdo, $originalUrl, $finalCode);
            $shortUrl = $baseUrl . '/' . $finalCode;

            $originalUrl = '';
            $customCode = '';
        } catch (PDOException $e) {
            $error = 'Произошла ошибка при сохранении ссылки. Попробуйте ещё раз.';
        }
    }
}

// Получаем последние 5 ссылок из базы данных для отображения в таблице
$recentLinks = getRecentLinks($pdo, 5);
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
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <main class="container">
        <div class="card">
            <div class="brand-header">
                <div class="logo-badge" title="URL Shortener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                </div>
                <h1>Сокращатель ссылок</h1>
                <p class="subtitle">Превратите длинный веб-адрес в короткую ссылку с QR-кодом и статистикой</p>
            </div>

            <?php if ($error !== null): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($shortUrl !== null): ?>
                <?php
                // Формируем ссылку на генерацию QR-кода (размер 140x140 пикселей)
                $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=6&data=' . urlencode($shortUrl);
                ?>
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

                    <!-- Блок с QR-кодом -->
                    <div class="qr-box">
                        <img src="<?= htmlspecialchars($qrCodeUrl, ENT_QUOTES, 'UTF-8') ?>" alt="QR-код ссылки" class="qr-image" width="110" height="110">
                        <div class="qr-info">
                            <strong>QR-код для телефона</strong>
                            <span>Наведите камеру смартфона, чтобы мгновенно открыть эту ссылку.</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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

            <!-- Секция недавно созданных ссылок и статистики кликов -->
            <?php if (!empty($recentLinks)): ?>
                <div class="recent-section">
                    <div class="recent-header">
                        <h2>Недавние ссылки</h2>
                        <span class="recent-note">Обновите страницу после клика, чтобы увидеть рост счётчика</span>
                    </div>
                    <div class="recent-list">
                        <?php foreach ($recentLinks as $item): ?>
                            <?php $itemShortUrl = $baseUrl . '/' . $item['short_code']; ?>
                            <div class="recent-item">
                                <div class="recent-links-col">
                                    <a href="<?= htmlspecialchars($itemShortUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="recent-short">
                                        /<?= htmlspecialchars($item['short_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <span class="recent-original" title="<?= htmlspecialchars($item['original_url'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($item['original_url'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <div class="recent-stats-col">
                                    <span class="clicks-badge" title="Количество переходов по ссылке">
                                        &#128065; <?= (int)$item['clicks'] ?> кликов
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="/assets/script.js"></script>
</body>
</html>