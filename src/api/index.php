<?php

/**
 * Vercel Serverless Entry Point for Laravel
 *
 * This file bootstraps the Laravel application in a Vercel serverless environment.
 * Vercel's filesystem is read-only except for /tmp, so we redirect all storage there.
 */

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
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Forward to Laravel's standard entry point
require __DIR__ . '/../public/index.php';
