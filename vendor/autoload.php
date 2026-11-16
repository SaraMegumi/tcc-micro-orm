<?php

declare(strict_types=1);

/**
 * Autoloader simples (PSR-4) para o namespace App\ORM -> src/.
 * Permite rodar os exemplos sem instalar o Composer. Se você rodar
 * "composer install" ou "composer dump-autoload", o Composer substitui
 * este arquivo pelo autoloader dele, que faz o mesmo.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\ORM\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
