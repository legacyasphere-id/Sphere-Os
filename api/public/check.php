<?php
ini_set('display_errors', '0');
header('Content-Type: application/json');
$base = dirname(__DIR__);
$out  = [];

register_shutdown_function(function () use (&$out) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $out['fatal'] = $e;
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode($out, JSON_PRETTY_PRINT);
    }
});

require_once $base.'/vendor/autoload.php';
$out['autoload'] = 'ok';

$app = require_once $base.'/bootstrap/app.php';
$out['app'] = 'ok';

// Env snapshot (safe subset — no secrets)
$out['env'] = [
    'APP_ENV'        => getenv('APP_ENV') ?: '(not set)',
    'DB_CONNECTION'  => getenv('DB_CONNECTION') ?: '(not set)',
    'DB_HOST'        => getenv('DB_HOST') ? '(set)' : '(not set)',
    'DB_PORT'        => getenv('DB_PORT') ?: '(not set)',
    'DB_DATABASE'    => getenv('DB_DATABASE') ? '(set)' : '(not set)',
    'DB_USERNAME'    => getenv('DB_USERNAME') ? '(set)' : '(not set)',
    'DB_PASSWORD'    => getenv('DB_PASSWORD') ? '(set)' : '(not set)',
    'SESSION_DRIVER' => getenv('SESSION_DRIVER') ?: '(not set)',
    'CACHE_STORE'    => getenv('CACHE_STORE') ?: '(not set)',
];

// Direct DB connection test (bypasses Laravel health checks)
try {
    $app->make('db')->connection()->getPdo();
    $out['db_connect'] = 'ok';
} catch (\Throwable $e) {
    $out['db_connect'] = $e->getMessage();
}

// Run migrations if ?migrate=1 is passed
if (isset($_GET['migrate'])) {
    try {
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        $exit = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $out['migrate'] = $exit === 0 ? 'ok' : 'exit:'.$exit;
        $out['migrate_output'] = \Illuminate\Support\Facades\Artisan::output();
    } catch (\Throwable $e) {
        $out['migrate_error'] = $e->getMessage();
    }
}

try {
    $kernel  = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $out['kernel'] = 'ok';
    $request = \Illuminate\Http\Request::create('/up', 'GET');
    $response = $kernel->handle($request);
    $out['up_status'] = $response->getStatusCode();
    if ($response->getStatusCode() !== 200) {
        $out['up_error'] = trim(strip_tags($response->getContent()));
    } else {
        $out['up_body'] = $response->getContent();
    }
} catch (\Throwable $e) {
    $out['request_error'] = $e->getMessage().' @ '.$e->getFile().':'.$e->getLine();
}

echo json_encode($out, JSON_PRETTY_PRINT);
