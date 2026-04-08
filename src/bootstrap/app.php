<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register JWT authentication middlewares
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuthentication::class,
        ]);

        // Configure CORS for API
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Configure encrypted cookies exception for JWT token
        $middleware->encryptCookies(except: [
            'jwt_token',  // Don't encrypt JWT token cookie
            'is_authenticated',  // Don't encrypt auth status cookie
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Vercel serverless: redirect storage to /tmp (filesystem is read-only)
// and fix package auto-discovery (vendor/ is at repo root, not src/vendor/)
if (getenv('VERCEL')) {
    $app->useStoragePath('/tmp/storage');

    // The root composer.json installs vendor/ at the repo root.
    // Laravel's basePath is src/, so it looks for src/vendor/ which doesn't exist.
    // Override PackageManifest to point at the real vendor directory.
    $repoRoot = dirname(__DIR__, 2);
    $app->singleton(
        \Illuminate\Foundation\PackageManifest::class,
        function () use ($repoRoot) {
            return new \Illuminate\Foundation\PackageManifest(
                new \Illuminate\Filesystem\Filesystem(),
                $repoRoot,
                '/tmp/cache/packages.php'
            );
        }
    );
}

return $app;
