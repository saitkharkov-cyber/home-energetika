<?php

spl_autoload_register(function ($className) {
    $prefix = 'FrameworkStandardization\\';
    $prefixLength = strlen($prefix);

    if (strncmp($prefix, $className, $prefixLength) !== 0) {
        return;
    }

    $relativeClass = substr($className, $prefixLength);
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
