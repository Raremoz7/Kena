<?php

use App\Http\Middleware\EnsureCanManage;
use App\Http\Middleware\EnsureCanManageOrganization;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'can-manage' => EnsureCanManage::class,
            'can-organize' => EnsureCanManageOrganization::class,
        ]);

        // Sem sessão de painel, /painel/* vai para o login do painel — nunca
        // para o /login do comprador, que é outro guard.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('painel', 'painel/*')
                ? route('painel.login')
                : route('login'),
        );

        // Webhooks externos não enviam token CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
