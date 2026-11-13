<?php
// Файл: 404.php
// Страница ошибки 404 (когда короткая ссылка не найдена)
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Ссылка не найдена</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <main class="container">
        <div class="card text-center">
            <div class="error-code">404</div>
            <h1>Короткая ссылка не найдена</h1>
            <p class="subtitle">Возможно, в адресе допущена опечатка, или такой ссылки никогда не существовало.</p>
            <a href="/" class="btn-primary inline-btn">Вернуться на главную</a>
        </div>
    </main>
</body>
</html>