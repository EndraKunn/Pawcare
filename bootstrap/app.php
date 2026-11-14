<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Route cart/order dipisah agar tidak bentrok dengan route /booking/{booking} lama.
            require __DIR__.'/../routes/atomic-checkout.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => EnsureRole::class]);
        // Webhook Payment Gateway diverifikasi menggunakan signature SHA-512.
        $middleware->validateCsrfTokens(except: [
            'webhook/payment/*',
        ]);
        // Guest yang membuka halaman terproteksi diarahkan ke login.
        $middleware->redirectGuestsTo(fn () => route('login'));
        // User yang sudah login dan membuka /login diarahkan ke dashboard sesuai role.
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
