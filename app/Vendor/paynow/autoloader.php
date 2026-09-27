<?php
/**
 * PSR-4 autoloader for the vendored Paynow-PHP-SDK (paynow.co.zw).
 * See LICENSE in this directory (GPL-3.0) — source:
 * https://github.com/paynow/Paynow-PHP-SDK
 */

require_once __DIR__ . '/src/helper.php';

spl_autoload_register(function ($class) {
    $root = 'Paynow\\';
    if (strncmp($class, $root, strlen($root)) !== 0) {
        return; // not a Paynow class — let other autoloaders handle it
    }
    $relative = substr($class, strlen($root));
    $filename = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($filename)) {
        require_once $filename;
    }
});
