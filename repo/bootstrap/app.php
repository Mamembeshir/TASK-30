<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'account.status'   => \App\Http\Middleware\AccountStatusMiddleware::class,
            'admin'            => \App\Http\Middleware\AdminMiddleware::class,
            'finance'          => \App\Http\Middleware\FinanceMiddleware::class,
            'credentialing'    => \App\Http\Middleware\CredentialingMiddleware::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\AccountStatusMiddleware::class,
        ]);

        // The /api/* routes use auth:web (session-based) so the session must be
        // started for both authentication and CSRF verification to work. We add
        // the same session + CSRF stack that the web group uses.
        //
        // ValidateCsrfToken automatically bypasses the check when
        // $app->runningUnitTests() is true, so feature tests are unaffected.
        // In production, POST/PUT/PATCH/DELETE requests to /api/* must supply
        // an X-CSRF-TOKEN header (or _token body field) matching the session
        // token — exactly the same contract as the Livewire component layer.
        $middleware->appendToGroup('api', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\VerifyApiCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
