<?php

use App\Http\Middleware\EnsureQseRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Audit HANDOFF-CODE-08 (SPEC-ADMIN-01 §0): alias ini dipakai di
        // routes/qse.php ('qse.role:curator') tapi belum pernah
        // didaftarkan — route kurator akan error "Target class [qse.role]
        // does not exist" kalau diakses tanpa baris ini.
        $middleware->alias([
            'qse.role' => EnsureQseRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
