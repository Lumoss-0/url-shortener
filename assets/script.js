// Файл: assets/script.js
// Функция копирования короткой ссылки в буфер обмена

document.addEventListener('DOMContentLoaded', function () {
    const copyBtn = document.getElementById('copy-btn');

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const urlToCopy = copyBtn.getAttribute('data-url');

            // Копируем текст через современный браузерный API
            navigator.clipboard.writeText(urlToCopy).then(function () {
                const originalText = copyBtn.textContent;
                copyBtn.textContent = 'Скопировано!';

                // Через 2 секунды возвращаем исходную надпись на кнопке
                setTimeout(function () {
                    copyBtn.textContent = originalText;
                }, 2000);
            }).catch(function () {
                alert('Не удалось скопировать ссылку автоматически. Выделите и скопируйте её вручную.');
            });
        });
    }
});