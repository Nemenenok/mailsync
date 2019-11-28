<?php

// Загрузка библиотек
require __DIR__ . '/vendor/autoload.php';
// Управляющий класс
require __DIR__ . '/mailSync.php';
// Загрузка файла конфигурации
$config = require __DIR__ . '/config.php';

// Создание инстанса и запуск обработки писем
(new mailSync($config, $argv))->processing();