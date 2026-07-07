<?php

require_once __DIR__ . '/../app/helpers.php';

use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TenantMiddleware;
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
        $middleware->appendToGroup('web', SetLocale::class);

        $middleware->alias([
            'identify.tenant' => IdentifyTenant::class,
            'tenant' => ResolveTenant::class,
            'tenant.required' => TenantMiddleware::class,
        ]);

            // ResolveTenant must run before auth resolves the user (TenantAwareUserProvider
            // needs the tenant context to decide whether the session user is accessible),
            // but Laravel's default priority list otherwise runs Authenticate first since
            // ResolveTenant isn't in it.
            $middleware->prependToPriorityList(
                before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
                prepend: ResolveTenant::class,
            );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
