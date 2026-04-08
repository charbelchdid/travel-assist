<?php

/**
 * Vercel Serverless Entry Point for Laravel
 *
 * This file bootstraps the Laravel application in a Vercel serverless environment.
 * Vercel's filesystem is read-only except for /tmp, so we redirect all storage there.
 */

// Show errors during debugging
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    // Create required storage directories in /tmp (the only writable path in serverless)
    $storageDirs = [
        '/tmp/storage',
        '/tmp/storage/app',
        '/tmp/storage/app/public',
        '/tmp/storage/framework',
        '/tmp/storage/framework/cache',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/storage/logs',
        '/tmp/cache',
    ];

    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Bootstrap Laravel directly (root vendor autoloader + src/ app)
    define('LARAVEL_START', microtime(true));

    require __DIR__ . '/../vendor/autoload.php';

    /** @var \Illuminate\Foundation\Application $app */
    $app = require_once __DIR__ . '/../src/bootstrap/app.php';

    $app->handleRequest(\Illuminate\Http\Request::capture());

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
