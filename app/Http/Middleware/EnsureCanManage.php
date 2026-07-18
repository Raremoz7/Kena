<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o painel a quem tem conta de painel (organizador ou staff).
 * Comprador logado não passa: são guards distintos.
 */
class EnsureCanManage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('painel')) {
            abort(403, 'Acesso restrito à equipe.');
        }

        return $next($request);
    }
}
