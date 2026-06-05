<?php
header('Content-Type: application/json');
$base = dirname(__DIR__);
echo json_encode([
    'php'            => PHP_VERSION,
    'pdo_pgsql'      => extension_loaded('pdo_pgsql'),
    'tmp_writable'   => is_writable('/tmp'),
    'storage_ok'     => is_writable($base.'/storage/logs'),
    'packages_php'   => file_exists($base.'/bootstrap/cache/packages.php'),
    'services_php'   => file_exists($base.'/bootstrap/cache/services.php'),
    'bootstrap_files'=> array_map('basename', glob($base.'/bootstrap/cache/*.php') ?: []),
    'vercel_env'     => getenv('VERCEL'),
    'app_key_set'    => strlen(getenv('APP_KEY') ?: '') > 0,
    'db_host_set'    => strlen(getenv('DB_HOST') ?: '') > 0,
]);
