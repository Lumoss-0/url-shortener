<?php
// Файл: includes/admin_functions.php
// Функции работы с базой данных для административной панели

/**
 * Поиск администратора по логину
 */
function findAdminByUsername(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

/**
 * Получение общей статистики для карточек сверху в админке
 */
function getAdminStats(PDO $pdo): array
{
    $totalLinks = (int)$pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
    $activeLinks = (int)$pdo->query('SELECT COUNT(*) FROM links WHERE is_active = 1')->fetchColumn();
    $totalClicks = (int)$pdo->query('SELECT COALESCE(SUM(clicks), 0) FROM links')->fetchColumn();
    $totalLogs = (int)$pdo->query('SELECT COUNT(*) FROM logs')->fetchColumn();

    return [
        'total_links'    => $totalLinks,
        'active_links'   => $activeLinks,
        'disabled_links' => $totalLinks - $activeLinks,
        'total_clicks'   => $totalClicks,
        'total_logs'     => $totalLogs,
    ];
}

/**
 * Получение всех ссылок (с поддержкой поиска по коду или длинному URL)
 */
function getAllLinks(PDO $pdo, string $search = ''): array
{
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt = $pdo->prepare(
            'SELECT id, original_url, short_code, clicks, creator_ip, is_active, created_at
             FROM links
             WHERE short_code LIKE ? OR original_url LIKE ?
             ORDER BY id DESC'
        );
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    return $pdo->query(
        'SELECT id, original_url, short_code, clicks, creator_ip, is_active, created_at
         FROM links
         ORDER BY id DESC'
    )->fetchAll();
}

/**
 * Получение одной ссылки по ID (нужно для формы редактирования)
 */
function getLinkById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, original_url, short_code, clicks, creator_ip, is_active, created_at
         FROM links
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Обновление ссылки (URL, короткий код и статус активности)
 */
function updateLink(PDO $pdo, int $id, string $originalUrl, string $shortCode, int $isActive): bool
{
    $stmt = $pdo->prepare(
        'UPDATE links
         SET original_url = ?, short_code = ?, is_active = ?
         WHERE id = ?'
    );
    return $stmt->execute([$originalUrl, $shortCode, $isActive, $id]);
}

/**
 * Переключение статуса ссылки (активна <-> отключена)
 * Возвращает новый статус (1 или 0) или null, если ссылка не найдена
 */
function toggleLinkStatus(PDO $pdo, int $id): ?int
{
    $link = getLinkById($pdo, $id);
    if ($link === null) {
        return null;
    }

    $newStatus = ((int)$link['is_active'] === 1) ? 0 : 1;
    $stmt = $pdo->prepare('UPDATE links SET is_active = ? WHERE id = ?');
    $stmt->execute([$newStatus, $id]);

    return $newStatus;
}

/**
 * Сброс счётчика кликов у конкретной ссылки
 */
function resetLinkClicks(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('UPDATE links SET clicks = 0 WHERE id = ?');
    return $stmt->execute([$id]);
}

/**
 * Удаление ссылки по ID
 */
function deleteLink(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM links WHERE id = ?');
    return $stmt->execute([$id]);
}

/**
 * Получение списка логов (с фильтром по типу события)
 */
function getLogs(PDO $pdo, string $eventType = '', int $limit = 100): array
{
    if ($eventType !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, event_type, message, ip_address, created_at
             FROM logs
             WHERE event_type = ?
             ORDER BY id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $eventType, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare(
        'SELECT id, event_type, message, ip_address, created_at
         FROM logs
         ORDER BY id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Очистка таблицы логов
 */
function clearLogs(PDO $pdo): void
{
    $pdo->exec('DELETE FROM logs');
}