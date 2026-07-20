<?php
// middleware class
use App\Http\Middleware\TrackAuditTrail;
use App\Http\Middleware\SetLocaleFromSession;
use App\Http\Middleware\EnsureTemporaryDeviceSession;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureAdminOnly;
use App\Http\Middleware\EnsureWarehouseAccess;

// laravel
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies (needed for reverse proxy / cloud deployment)
        $middleware->trustProxies(at: '*');
        // Set language from session, append after other web middleware
        $middleware->appendToGroup('web', SetLocaleFromSession::class);
        $middleware->appendToGroup('web', EnsureTemporaryDeviceSession::class);
        $middleware->appendToGroup('web', TrackAuditTrail::class);
        $middleware->appendToGroup('web', EnsurePasswordChanged::class);
        // 註冊中間件別名
        $middleware->alias([
            'admin.only'       => EnsureAdminOnly::class,
            'warehouse.access' => EnsureWarehouseAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
