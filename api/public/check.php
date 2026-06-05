<?php
ini_set('display_errors', '0');
header('Content-Type: application/json');
$base = dirname(__DIR__);
$out  = [];

// Capture PHP fatal errors
register_shutdown_function(function () use (&$out) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $out['fatal'] = $e;
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode($out, JSON_PRETTY_PRINT);
    }
});

$out['autoload'] = 'pending';
require_once $base.'/vendor/autoload.php';
$out['autoload'] = 'ok';

// Test config files with env() available
foreach (glob($base.'/config/*.php') as $cfg) {
    $name = basename($cfg, '.php');
    try {
        require_once $cfg;
        $out['config'][$name] = 'ok';
    } catch (\Throwable $e) {
        $out['config'][$name] = $e->getMessage().' @ '.$e->getFile().':'.$e->getLine();
    }
}

// Try creating the Application
$out['app'] = 'pending';
try {
    $app = require_once $base.'/bootstrap/app.php';
    $out['app'] = get_class($app);
} catch (\Throwable $e) {
    $out['app'] = 'ERROR: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine();
}

echo json_encode($out, JSON_PRETTY_PRINT);
