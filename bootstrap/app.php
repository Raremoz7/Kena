<?php

use App\Http\Middleware\EnsureCanManage;
use App\Http\Middleware\EnsureCanManageOrganization;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
            'platform-admin' => EnsurePlatformAdmin::class,
        ]);

        // Multi-tenant: a organização do painel precisa estar definida ANTES de
        // resolver {event}, {order} etc. da URL, senão o filtro não vale no
        // route model binding e um id de outro organizador abriria a página.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureCanManage::class);

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
        // JSON para as rotas api/* e para qualquer chamada fetch que peça JSON
        // (checkout, código de e-mail, ações do painel). Sem isso, um erro de
        // validação virava redirect 302 → o fetch seguia, recebia HTML com 200 e
        // tratava como sucesso. Visitas Inertia continuam com redirect + errors.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->expectsJson() && ! $request->hasHeader('X-Inertia')),
        );
    })->create();
