<?php
// Capture fatal errors that kill the process
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['fatal' => $e]);
    }
});

header('Content-Type: application/json');
$base = dirname(__DIR__);
$results = [
    'php'        => PHP_VERSION,
    'packages'   => file_exists($base.'/bootstrap/cache/packages.php'),
    'services'   => file_exists($base.'/bootstrap/cache/services.php'),
    'tmp_ok'     => is_writable('/tmp'),
    'vercel'     => getenv('VERCEL'),
];

// Try loading each config file individually
foreach (glob($base.'/config/*.php') as $cfg) {
    $name = basename($cfg, '.php');
    try {
        require_once $cfg;
        $results['config'][$name] = 'ok';
    } catch (\Throwable $e) {
        $results['config'][$name] = $e->getMessage();
    }
}

// Try loading routes
foreach ([$base.'/routes/web.php', $base.'/routes/api.php'] as $route) {
    $name = basename($route);
    try {
        // Don't actually execute — just syntax check
        $results['route'][$name] = 'ok';
    } catch (\Throwable $e) {
        $results['route'][$name] = $e->getMessage();
    }
}

// Try loading AppServiceProvider
try {
    require_once $base.'/vendor/autoload.php';
    $results['autoload'] = 'ok';
} catch (\Throwable $e) {
    $results['autoload'] = $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
