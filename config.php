<?php
return [
    // Настройки почтового сервера
    'email_config' => [
        // Адрес почты
        'host' => 'imap.gmail.com',
        // Имя пользователя (E-mail)
        'email' => '',
        // Пароль
        'password' => '',
        // Папка получения писем (По умолчания "Входящие")
        'folder' => 'INBOX',
    ],
    // Настройки YouTrack
    'youtrack_config' => [
        // API URL
        'apiBaseUri' => '',
        // Токен авторизации
        'apiToken' => '',
    ],
];
