<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o painel a quem tem conta de painel (organizador ou staff) e
 * define a organização dele como contexto. Comprador logado não passa: são
 * guards distintos.
 */
class EnsureCanManage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('painel');
        if (! $user instanceof PanelUser) {
            abort(403, 'Acesso restrito à equipe.');
        }

        // Multi-tenant: daqui em diante o painel só enxerga a organização do
        // usuário. Limpa no fim para não vazar para a próxima requisição.
        CurrentOrganization::set($user->organization_id);

        try {
            return $next($request);
        } finally {
            CurrentOrganization::clear();
        }
    }
}
