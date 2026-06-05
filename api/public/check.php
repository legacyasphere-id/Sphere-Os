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

try {
    $kernel  = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $out['kernel'] = 'ok';
    $request = \Illuminate\Http\Request::create('/up', 'GET');
    $response = $kernel->handle($request);
    $out['up_status']  = $response->getStatusCode();
    $out['up_body']    = $response->getContent();
} catch (\Throwable $e) {
    $out['request_error'] = $e->getMessage().' @ '.$e->getFile().':'.$e->getLine();
}

echo json_encode($out, JSON_PRETTY_PRINT);
