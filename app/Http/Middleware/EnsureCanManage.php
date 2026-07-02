<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o painel à equipe (organizer/staff ou admin).
 */
class EnsureCanManage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->canManageEvents() || $user->is_admin)) {
            abort(403, 'Acesso restrito à equipe.');
        }

        return $next($request);
    }
}
