<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Telas e ações da plataforma (organizações, SMTP, Wallet): só o admin da plataforma. */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('painel');

        if (! $user instanceof PanelUser || ! $user->isPlatformAdmin()) {
            abort(403, 'Ação restrita ao admin da plataforma.');
        }

        return $next($request);
    }
}
