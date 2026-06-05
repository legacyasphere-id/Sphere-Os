<?php
header('Content-Type: application/json');
echo json_encode([
    'php'        => PHP_VERSION,
    'writable'   => is_writable('/tmp'),
    'storage_ok' => is_writable(dirname(__DIR__).'/storage/logs'),
    'pdo_pgsql'  => extension_loaded('pdo_pgsql'),
    'pdo_mysql'  => extension_loaded('pdo_mysql'),
    'extensions' => get_loaded_extensions(),
    'vercel_env' => getenv('VERCEL'),
]);
