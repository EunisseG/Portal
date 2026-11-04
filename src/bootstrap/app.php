<?php

use App\Http\Middleware\EnsureUserIsNotBlocked;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de un proxy inverso (nginx del host que termina TLS), confiar
        // en los headers X-Forwarded-* para que Laravel genere URLs https://
        // correctas (enlaces de correo, cookies seguras, detección de esquema).
        // Es seguro porque solo hay un proxy local delante del contenedor.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'not.blocked' => EnsureUserIsNotBlocked::class,
        ]);

        // Todo usuario autenticado en el área web pasa por el chequeo de bloqueo.
        $middleware->web(append: [
            EnsureUserIsNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
