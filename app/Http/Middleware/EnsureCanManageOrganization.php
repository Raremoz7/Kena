<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a gestão sensível (eventos, cupons, locais, pedidos, config, equipe)
 * a organizador. Staff só alcança o check-in.
 */
class EnsureCanManageOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('painel');

        if ($user && ! $user->canManageOrganization() && $request->isMethod('GET') && ! $request->expectsJson()) {
            // Staff que abre o link de uma tela de organizador (ex.: alguém
            // mandou o link da Equipe) vai para o check-in, não para um 403
            // que parece erro de acesso.
            return redirect()->route('admin.checkin');
        }

        if (! $user || ! $user->canManageOrganization()) {
            abort(403, 'Ação restrita a organizadores.');
        }

        return $next($request);
    }
}
