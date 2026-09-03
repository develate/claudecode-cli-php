<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Develate\\ClaudecodeCli\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($path)) {
        require $path;
    }
});
