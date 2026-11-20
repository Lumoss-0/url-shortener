<?php
// Файл: admin.php
// Административная панель: управление ссылками и просмотр логов (/admin)

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/admin_functions.php';

applySecurityHeaders();
startSecureSession();
requireAdminAuth();

$pdo = getDbConnection();
$csrfToken = getCsrfToken();
$baseUrl = getBaseUrl();
$adminUsername = (string)($_SESSION['admin_username'] ?? 'admin');

// Выбранная вкладка: links (ссылки) или logs (логи)
$activeTab = (($_GET['tab'] ?? 'links') === 'logs') ? 'logs' : 'links';
$searchQuery = trim($_GET['q'] ?? '');
$logFilter = trim($_GET['event'] ?? '');

// Помощник для сохранения временного уведомления (flash-сообщения) и редиректа
function redirectAdminWithFlash(string $type, string $text, string $url = '/admin'): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'text' => $text];
    header('Location: ' . $url);
    exit;
}

// Обработка всех действий администратора (строго через POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $postedToken = $_POST['csrf_token'] ?? null;
    $clientIp = getClientIp();

    if (!verifyCsrfToken(is_string($postedToken) ? $postedToken : null)) {
        redirectAdminWithFlash('error', 'Ошибка безопасности (неверный CSRF-токен). Повторите действие.');
    }

    // 1. Выход из админки
    if ($action === 'logout') {
        writeLog($pdo, 'admin_logout', 'Администратор ' . $adminUsername . ' вышел из панели', $clientIp);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        header('Location: /admin/login');
        exit;
    }

    // 2. Редактирование ссылки
    if ($action === 'update_link') {
        $id = (int)($_POST['id'] ?? 0);
        $originalUrl = trim($_POST['original_url'] ?? '');
        $shortCode = trim($_POST['short_code'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $existing = getLinkById($pdo, $id);
        if ($existing === null) {
            redirectAdminWithFlash('error', 'Ссылка для редактирования не найдена.');
        }

        $urlError = validateUrl($originalUrl);
        if ($urlError !== null) {
            redirectAdminWithFlash('error', $urlError, '/admin?edit=' . $id);
        }

        $codeError = validateCustomCode($shortCode);
        if ($codeError !== null) {
            redirectAdminWithFlash('error', $codeError, '/admin?edit=' . $id);
        }

        if (isCodeTaken($pdo, $shortCode, $id)) {
            redirectAdminWithFlash('error', 'Короткий код /' . $shortCode . ' уже используется другой ссылкой.', '/admin?edit=' . $id);
        }

        updateLink($pdo, $id, $originalUrl, $shortCode, $isActive);
        writeLog(
            $pdo,
            'admin_edit',
            'Админ изменил ссылку #' . $id . ' (/' . $existing['short_code'] . ' -> /' . $shortCode . ')',
            $clientIp
        );

        redirectAdminWithFlash('success', 'Ссылка /' . $shortCode . ' успешно обновлена.');
    }

    // 3. Включение / отключение ссылки
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $link = getLinkById($pdo, $id);
        $newStatus = toggleLinkStatus($pdo, $id);

        if ($link !== null && $newStatus !== null) {
            $statusWord = ($newStatus === 1) ? 'включена' : 'отключена';
            writeLog($pdo, 'admin_toggle', 'Ссылка /' . $link['short_code'] . ' ' . $statusWord, $clientIp);
            redirectAdminWithFlash('success', 'Ссылка /' . $link['short_code'] . ' ' . $statusWord . '.');
        }

        redirectAdminWithFlash('error', 'Ссылка не найдена.');
    }

    // 4. Сброс счётчика кликов
    if ($action === 'reset_clicks') {
        $id = (int)($_POST['id'] ?? 0);
        $link = getLinkById($pdo, $id);

        if ($link !== null) {
            resetLinkClicks($pdo, $id);
            writeLog($pdo, 'admin_reset_clicks', 'Сброшены клики у ссылки /' . $link['short_code'], $clientIp);
            redirectAdminWithFlash('success', 'Счётчик кликов у /' . $link['short_code'] . ' сброшен.');
        }

        redirectAdminWithFlash('error', 'Ссылка не найдена.');
    }

    // 5. Удаление ссылки
    if ($action === 'delete_link') {
        $id = (int)($_POST['id'] ?? 0);
        $link = getLinkById($pdo, $id);

        if ($link !== null) {
            deleteLink($pdo, $id);
            writeLog($pdo, 'admin_delete', 'Удалена ссылка /' . $link['short_code'] . ' (' . $link['original_url'] . ')', $clientIp);
            redirectAdminWithFlash('success', 'Ссылка /' . $link['short_code'] . ' удалена.');
        }

        redirectAdminWithFlash('error', 'Ссылка не найдена.');
    }

    // 6. Очистка журнала логов
    if ($action === 'clear_logs') {
        clearLogs($pdo);
        writeLog($pdo, 'admin_clear_logs', 'Администратор ' . $adminUsername . ' очистил журнал логов', $clientIp);
        redirectAdminWithFlash('success', 'Журнал логов очищен.', '/admin?tab=logs');
    }
}

// Забираем одноразовое уведомление из сессии (если оно есть)
$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

// Если нажата кнопка «Редактировать» (?edit=ID), загружаем эту ссылку
$editLink = null;
if ($activeTab === 'links' && isset($_GET['edit'])) {
    $editLink = getLinkById($pdo, (int)$_GET['edit']);
}

$stats = getAdminStats($pdo);
$links = ($activeTab === 'links') ? getAllLinks($pdo, $searchQuery) : [];
$logs = ($activeTab === 'logs') ? getLogs($pdo, $logFilter, 100) : [];

// Читаемые названия типов событий для фильтра и таблицы логов
$eventLabels = [
    'link_created'       => 'Создание ссылки',
    'redirect'           => 'Переход',
    'not_found'          => 'Ошибка 404',
    'admin_login'        => 'Вход админа',
    'admin_login_failed' => 'Неудачный вход',
    'admin_logout'       => 'Выход админа',
    'admin_edit'         => 'Правка ссылки',
    'admin_toggle'       => 'Смена статуса',
    'admin_reset_clicks' => 'Сброс кликов',
    'admin_delete'       => 'Удаление ссылки',
    'admin_clear_logs'   => 'Очистка логов',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — URL Shortener</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="admin-body">
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <main class="container container-wide">
        <div class="card admin-card">
            <!-- Верхняя шапка админки -->
            <div class="admin-topbar">
                <div>
                    <h1>Панель администратора</h1>
                    <p class="subtitle">Вы вошли как <strong><?= e($adminUsername) ?></strong></p>
                </div>
                <div class="admin-topbar-actions">
                    <a href="/" class="btn-secondary">На сайт</a>
                    <form method="POST" action="/admin" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn-danger-outline">Выйти</button>
                    </form>
                </div>
            </div>

            <!-- Временное уведомление об успехе или ошибке -->
            <?php if (is_array($flash)): ?>
                <div class="alert <?= ($flash['type'] === 'error') ? 'alert-error' : 'alert-success' ?>">
                    <?= e((string)$flash['text']) ?>
                </div>
            <?php endif; ?>

            <!-- Карточки сводной статистики -->
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-label">Всего ссылок</span>
                    <strong class="stat-value"><?= $stats['total_links'] ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Активных / Откл.</span>
                    <strong class="stat-value"><?= $stats['active_links'] ?> / <?= $stats['disabled_links'] ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Всего переходов</span>
                    <strong class="stat-value"><?= $stats['total_clicks'] ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Записей в логах</span>
                    <strong class="stat-value"><?= $stats['total_logs'] ?></strong>
                </div>
            </div>

            <!-- Переключатель вкладок -->
            <div class="admin-tabs">
                <a href="/admin?tab=links" class="tab-btn <?= ($activeTab === 'links') ? 'active' : '' ?>">
                    Управление ссылками
                </a>
                <a href="/admin?tab=logs" class="tab-btn <?= ($activeTab === 'logs') ? 'active' : '' ?>">
                    Журнал логов
                </a>
            </div>

            <?php if ($activeTab === 'links'): ?>
                <!-- Форма редактирования выбранной ссылки -->
                <?php if ($editLink !== null): ?>
                    <div class="edit-panel">
                        <div class="edit-panel-header">
                            <h2>Редактирование ссылки #<?= (int)$editLink['id'] ?></h2>
                            <a href="/admin" class="admin-back-link">Отмена</a>
                        </div>
                        <form method="POST" action="/admin">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="update_link">
                            <input type="hidden" name="id" value="<?= (int)$editLink['id'] ?>">

                            <div class="edit-grid">
                                <div class="form-group">
                                    <label for="edit_url">Исходная ссылка</label>
                                    <input
                                        type="url"
                                        id="edit_url"
                                        name="original_url"
                                        value="<?= e($editLink['original_url']) ?>"
                                        required
                                    >
                                </div>
                                <div class="form-group">
                                    <label for="edit_code">Короткий код</label>
                                    <input
                                        type="text"
                                        id="edit_code"
                                        name="short_code"
                                        value="<?= e($editLink['short_code']) ?>"
                                        maxlength="<?= MAX_CUSTOM_CODE_LENGTH ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="edit-footer">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_active" value="1" <?= ((int)$editLink['is_active'] === 1) ? 'checked' : '' ?>>
                                    <span>Ссылка активна (работает перенаправление)</span>
                                </label>
                                <button type="submit" class="btn-primary inline-btn" style="margin-top: 0;">Сохранить изменения</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Поиск по ссылкам -->
                <form method="GET" action="/admin" class="filter-bar">
                    <input type="hidden" name="tab" value="links">
                    <input
                        type="text"
                        name="q"
                        placeholder="Поиск по короткому коду или URL..."
                        value="<?= e($searchQuery) ?>"
                    >
                    <button type="submit" class="btn-secondary">Найти</button>
                    <?php if ($searchQuery !== ''): ?>
                        <a href="/admin?tab=links" class="btn-secondary">Сбросить</a>
                    <?php endif; ?>
                </form>

                <!-- Таблица всех ссылок -->
                <?php if (empty($links)): ?>
                    <p class="empty-state">Ссылок пока нет (или по запросу ничего не найдено).</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Короткая ссылка</th>
                                    <th>Исходный URL</th>
                                    <th>Клики</th>
                                    <th>Статус</th>
                                    <th>IP / Дата</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $row): ?>
                                    <?php $fullShort = $baseUrl . '/' . $row['short_code']; ?>
                                    <tr>
                                        <td>#<?= (int)$row['id'] ?></td>
                                        <td>
                                            <a href="<?= e($fullShort) ?>" target="_blank" class="recent-short">
                                                /<?= e($row['short_code']) ?>
                                            </a>
                                        </td>
                                        <td class="url-cell" title="<?= e($row['original_url']) ?>">
                                            <?= e($row['original_url']) ?>
                                        </td>
                                        <td>
                                            <span class="clicks-badge"><?= (int)$row['clicks'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ((int)$row['is_active'] === 1): ?>
                                                <span class="status-badge status-active">Активна</span>
                                            <?php else: ?>
                                                <span class="status-badge status-disabled">Отключена</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="meta-cell">
                                            <div><?= e($row['creator_ip'] !== '' ? $row['creator_ip'] : '—') ?></div>
                                            <small><?= e(formatDateTime($row['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                <a href="/admin?edit=<?= (int)$row['id'] ?>" class="btn-mini">Править</a>

                                                <form method="POST" action="/admin" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="btn-mini">
                                                        <?= ((int)$row['is_active'] === 1) ? 'Откл.' : 'Вкл.' ?>
                                                    </button>
                                                </form>

                                                <form method="POST" action="/admin" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="reset_clicks">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="btn-mini" title="Обнулить счётчик кликов">0 кликов</button>
                                                </form>

                                                <form method="POST" action="/admin" class="inline-form" onsubmit="return confirm('Удалить ссылку /<?= e($row['short_code']) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_link">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="btn-mini btn-mini-danger">Удалить</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Вкладка «Журнал логов» -->
                <div class="filter-bar">
                    <form method="GET" action="/admin" class="inline-form filter-select-group">
                        <input type="hidden" name="tab" value="logs">
                        <select name="event" class="admin-select" onchange="this.form.submit()">
                            <option value="">Все события</option>
                            <?php foreach ($eventLabels as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($logFilter === $key) ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($logFilter !== ''): ?>
                            <a href="/admin?tab=logs" class="btn-secondary">Сбросить</a>
                        <?php endif; ?>
                    </form>

                    <form method="POST" action="/admin" class="inline-form" onsubmit="return confirm('Очистить все записи в журнале логов?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn-danger-outline">Очистить логи</button>
                    </form>
                </div>

                <?php if (empty($logs)): ?>
                    <p class="empty-state">Записей в логах пока нет.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Дата и время</th>
                                    <th>Событие</th>
                                    <th>Описание</th>
                                    <th>IP-адрес</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php $type = (string)$log['event_type']; ?>
                                    <tr>
                                        <td class="meta-cell" style="white-space: nowrap;"><?= e(formatDateTime($log['created_at'])) ?></td>
                                        <td>
                                            <span class="log-badge log-<?= e($type) ?>">
                                                <?= e($eventLabels[$type] ?? $type) ?>
                                            </span>
                                        </td>
                                        <td class="url-cell" title="<?= e($log['message']) ?>"><?= e($log['message']) ?></td>
                                        <td class="meta-cell"><?= e($log['ip_address']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>