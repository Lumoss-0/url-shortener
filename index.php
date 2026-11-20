<?php
// Файл: index.php
// Главная страница: форма с CSRF-защитой, QR-код и личная история ссылок

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

applySecurityHeaders();
startSecureSession();

$pdo = getDbConnection();
$ownerToken = getOrCreateOwnerToken();
$csrfToken = getCsrfToken();
$baseUrl = getBaseUrl();

$error = null;
$shortUrl = null;
$originalUrl = '';
$customCode = '';
$finalCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $originalUrl = trim($_POST['url'] ?? '');
    $customCode  = trim($_POST['custom_code'] ?? '');
    $postedToken = $_POST['csrf_token'] ?? null;

    // 1. Проверяем CSRF-токен формы
    if (!verifyCsrfToken(is_string($postedToken) ? $postedToken : null)) {
        $error = 'Ошибка безопасности (неверный CSRF-токен). Обновите страницу и попробуйте снова.';
    } else {
        // 2. Проверяем корректность длинной ссылки
        $error = validateUrl($originalUrl);
    }

    // 3. Проверяем псевдоним или генерируем код через uniqid()
    if ($error === null) {
        if ($customCode !== '') {
            $error = validateCustomCode($customCode);

            if ($error === null && isCodeTaken($pdo, $customCode)) {
                $error = 'Этот псевдоним уже занят. Пожалуйста, придумайте другой.';
            }

            $finalCode = $customCode;
        } else {
            $finalCode = generateUniqueCode($pdo, SHORT_CODE_LENGTH);
        }
    }

    // 4. Сохраняем ссылку и записываем событие в лог
    if ($error === null) {
        try {
            $clientIp = getClientIp();
            saveLink($pdo, $originalUrl, $finalCode, $ownerToken, $clientIp);
            writeLog($pdo, 'link_created', 'Создана ссылка /' . $finalCode . ' -> ' . $originalUrl, $clientIp);

            $shortUrl = $baseUrl . '/' . $finalCode;
            $originalUrl = '';
            $customCode = '';
        } catch (PDOException $e) {
            $error = 'Произошла ошибка при сохранении ссылки. Попробуйте ещё раз.';
        }
    }
}

// Загружаем только ссылки, созданные в этом браузере (приватный список)
$recentLinks = getRecentLinksByOwner($pdo, $ownerToken, RECENT_LINKS_LIMIT);
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
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($shortUrl !== null): ?>
                <?php
                $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=6&data=' . urlencode($shortUrl);
                ?>
                <div class="alert alert-success">
                    <p class="result-label">&#10024; Ваша короткая ссылка готова:</p>
                    <div class="result-box">
                        <a href="<?= e($shortUrl) ?>"
                           target="_blank"
                           id="short-url-link"
                           class="short-link">
                            <?= e($shortUrl) ?>
                        </a>
                        <button type="button" id="copy-btn" class="btn-secondary" data-url="<?= e($shortUrl) ?>">
                            Копировать
                        </button>
                    </div>

                    <div class="qr-box">
                        <img src="<?= e($qrCodeUrl) ?>" alt="QR-код ссылки" class="qr-image" width="110" height="110">
                        <div class="qr-info">
                            <strong>QR-код для телефона</strong>
                            <span>Наведите камеру смартфона, чтобы мгновенно открыть эту ссылку.</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="/" novalidate>
                <!-- Скрытое поле с CSRF-токеном для защиты от подделки запросов -->
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="form-group">
                    <label for="url">Длинная ссылка <span class="required">*</span></label>
                    <input
                        type="url"
                        id="url"
                        name="url"
                        placeholder="https://example.com/very/long/address"
                        value="<?= e($originalUrl) ?>"
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
                        value="<?= e($customCode) ?>"
                        maxlength="<?= MAX_CUSTOM_CODE_LENGTH ?>"
                    >
                    <small class="hint">
                        Разрешены латинские буквы, цифры, дефис (-) и подчёркивание (_), от <?= MIN_CUSTOM_CODE_LENGTH ?> до <?= MAX_CUSTOM_CODE_LENGTH ?> символов.
                    </small>
                </div>

                <button type="submit" class="btn-primary">
                    <span class="shrink-arrow arrow-left">&rarr;</span>
                    <span>Сократить ссылку</span>
                    <span class="shrink-arrow arrow-right">&larr;</span>
                </button>
            </form>

            <?php if (!empty($recentLinks)): ?>
                <div class="recent-section">
                    <div class="recent-header">
                        <h2>Мои недавние ссылки</h2>
                        <span class="recent-note">Приватный список — виден только в вашем браузере</span>
                    </div>
                    <div class="recent-list">
                        <?php foreach ($recentLinks as $item): ?>
                            <?php $itemShortUrl = $baseUrl . '/' . $item['short_code']; ?>
                            <div class="recent-item">
                                <div class="recent-links-col">
                                    <a href="<?= e($itemShortUrl) ?>" target="_blank" class="recent-short">
                                        /<?= e($item['short_code']) ?>
                                    </a>
                                    <span class="recent-original" title="<?= e($item['original_url']) ?>">
                                        <?= e($item['original_url']) ?>
                                    </span>
                                </div>
                                <div class="recent-stats-col">
                                    <span class="clicks-badge" title="Количество переходов по ссылке">
                                        &#128065; <?= (int)$item['clicks'] ?>
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