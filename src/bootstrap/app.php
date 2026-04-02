<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
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
