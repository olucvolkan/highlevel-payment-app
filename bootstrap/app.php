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
        // Exclude CSRF verification for specific routes
        $middleware->validateCsrfTokens(except: [
            'paytr/test',
            'paytr/credentials',
            'api/*',
            'payments/callback',
            'webhooks/*',
            'oauth/uninstall',
        ]);
    })
    ->withSchedule(function ($schedule) {
        // Proactively refresh OAuth tokens that will expire in next 10 minutes
        // Runs every 5 minutes to ensure tokens are always fresh
        $schedule->command('tokens:refresh-expiring --minutes=10')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
