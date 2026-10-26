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
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            return $request->is('api/*') ? null : '/login';
        });
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        $middleware->appendToGroup('api', [\App\Http\Middleware\CorsMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Trả về JSON 401 thay vì redirect tới route "login" (không tồn tại trong SPA thuần API)
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
