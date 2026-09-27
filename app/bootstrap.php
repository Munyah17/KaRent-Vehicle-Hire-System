<?php
/**
 * Application bootstrap: config, autoloading, session, DB, error handling.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
// Supports both layouts: dedicated public/ dir, or a flat deploy where
// public/'s contents were placed alongside app/ inside public_html.
define('PUBLIC_PATH', is_dir(BASE_PATH . '/public') ? BASE_PATH . '/public' : BASE_PATH);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('PUBLIC_UPLOAD_PATH', PUBLIC_PATH . '/uploads');

$configFile = BASE_PATH . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = BASE_PATH . '/config/config.sample.php';
}
$GLOBALS['config'] = require $configFile;
$config = $GLOBALS['config'];

date_default_timezone_set($config['timezone'] ?? 'UTC');

if (($config['env'] ?? 'dev') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('error_log', STORAGE_PATH . '/logs/php-errors.log');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Autoload App\* classes from app/.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require_once APP_PATH . '/helpers.php';
// Vendored Paynow-PHP-SDK (paynow.co.zw) — no composer needed on cPanel.
require_once APP_PATH . '/Vendor/paynow/autoloader.php';

// Secure session defaults.
if (session_status() === PHP_SESSION_NONE) {
    session_name('VHMSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}
