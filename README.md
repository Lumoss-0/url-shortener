# Сокращатель ссылок

Учебный проект на PHP 8.2+ для создания коротких ссылок и перехода по ним. Данные хранятся в SQLite через PDO.

## Запуск через Docker

Установите и запустите Docker Desktop, затем откройте терминал в папке проекта и выполните:

```powershell
docker compose up --build -d
```

Откройте [http://localhost:8000](http://localhost:8000).

SQLite-файл хранится в Docker volume и сохраняется после остановки контейнера. Остановить приложение:

```powershell
docker compose down
```

Чтобы удалить приложение вместе с сохранённой базой данных, выполните `docker compose down -v`.

## Запуск без Docker

Нужны PHP 8.2+ с расширением `pdo_sqlite`. Из корневой папки проекта запустите:

```powershell
php -S localhost:8000 router.php
```

Откройте [http://localhost:8000](http://localhost:8000). Файл `database.sqlite` будет создан автоматически.