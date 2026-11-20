# AGENTS.md

## Команды

- Локальный запуск без Docker (PHP 8.2+ с расширением `pdo_sqlite`): `php -S localhost:8000 router.php` — сайт откроется на `http://localhost:8000`. Без указания `router.php` короткие ссылки и `/admin` работать не будут.
- Запуск через Docker: `docker compose up --build -d` — сайт откроется на `http://localhost:8080` (в `compose.yaml` проброшен порт `127.0.0.1:8080:8000`, доступ открыт только локально).
- Остановка Docker: `docker compose down` (с удалением volume и базы: `docker compose down -v`).
- Быстрая проверка синтаксиса PHP в PowerShell: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`.
- Сброс локальной БД: остановите сервер и удалите `database.sqlite` — при первом запросе `initDatabase()` в `config/db.php` пересоздаст файл, все таблицы и учётную запись администратора.
- Проект на нативном PHP без Composer, Laravel, npm и сторонних библиотек — не добавляйте зависимости без явного запроса.

## Данные и авторизация

- База данных: SQLite (`database.sqlite` в корне проекта; в Docker путь задаётся через переменную среды `DB_PATH=/data/database.sqlite`, константа `DB_PATH` в `config/config.php`). Файл `database.sqlite` добавлен в `.gitignore`.
- Подключение к БД выполняется только через функцию `getDbConnection(): PDO` (`config/db.php`). Миграции старых таблиц и сидирование админа происходят автоматически в `initDatabase()`.
- Таблицы: `links` (ссылки, `clicks`, `owner_token`, `creator_ip`, `is_active`), `logs` (журнал событий), `admins` (логин и `password_hash`).
- Время в SQLite хранится в UTC (`CURRENT_TIMESTAMP`); для отображения используется `formatDateTime()` и константа `APP_TIMEZONE` (по умолчанию `Asia/Yekaterinburg`, UTC+5).
- Регистрации нет. Маршруты админки: `/admin` (`admin.php`) и `/admin/login` (`admin_login.php`).
- Логин администратора по умолчанию: `admin` / `admin123` (создаётся только если таблица `admins` пуста; можно переопределить переменными среды `ADMIN_LOGIN` и `ADMIN_PASSWORD`).
- Блок «Мои недавние ссылки» на главной странице привязан к HttpOnly cookie `shortener_owner` (`owner_token`), а не к IP — пользователь видит только ссылки своего браузера.

## Архитектура и безопасность

- Все константы и лимиты вынесены в `config/config.php` (`MAX_URL_LENGTH`, `SHORT_CODE_LENGTH`, `RESERVED_SHORT_CODES`, `MAX_LOGIN_ATTEMPTS`, `LOGIN_LOCKOUT_SECONDS`).
- Короткие коды генерируются через `substr(uniqid(), -$length)` в `generateUniqueCode()` (`includes/functions.php`) с проверкой уникальности.
- Все SQL-запросы выполняются только через подготовленные выражения PDO (`prepare` + `execute`, `PDO::ATTR_EMULATE_PREPARES => false`).
- Весь вывод в HTML экранируется через `e()` (`includes/security.php`).
- Все POST-формы (`index.php`, `admin_login.php`, `admin.php`) обязаны проверять `csrf_token` через `verifyCsrfToken()`.
- При входе в админку действует блокировка после `MAX_LOGIN_ATTEMPTS` (5) неудачных попыток на `LOGIN_LOCKOUT_SECONDS` (300 сек) и регенерация сессии (`session_regenerate_id(true)`).
- `router.php` и `.htaccess` блокируют прямой доступ из браузера к `/config`, `/includes`, `.sqlite`, `.sql`, `.md`, `.yaml`, `Dockerfile` — не ослабляйте эти правила.
- Деплой: Render.com (Web Service из `Dockerfile`, ветка `main`). На бесплатном тарифе контейнер засыпает после простоя, и локальный файл SQLite сбрасывается при перезапуске.