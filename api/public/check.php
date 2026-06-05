<?php
header('Content-Type: application/json');

$base = dirname(__DIR__);

// Catch any fatal error from trying to boot Laravel
$error = null;
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error) {
    $error = "$errstr in $errfile:$errline";
});

try {
    ob_start();
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    ob_end_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    $error = $e->getMessage().' in '.$e->getFile().':'.$e->getLine();
}

restore_error_handler();

$cacheFiles = glob($base.'/bootstrap/cache/*.php') ?: [];

echo json_encode([
    'php'           => PHP_VERSION,
    'pdo_pgsql'     => extension_loaded('pdo_pgsql'),
    'tmp_writable'  => is_writable('/tmp'),
    'storage_ok'    => is_writable($base.'/storage/logs'),
    'bootstrap_cache' => array_map('basename', $cacheFiles),
    'packages_php'  => file_exists($base.'/bootstrap/cache/packages.php'),
    'services_php'  => file_exists($base.'/bootstrap/cache/services.php'),
    'bootstrap_error' => $error,
    'vercel_env'    => getenv('VERCEL'),
], JSON_PRETTY_PRINT);
