<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

// Carrega automaticamente Models e Controllers
spl_autoload_register(function (string $classe): void {
    foreach (['models', 'controllers'] as $pasta) {
        $arquivo = __DIR__ . "/$pasta/$classe.php";
        if (is_file($arquivo)) {
            require_once $arquivo;
            return;
        }
    }
});
