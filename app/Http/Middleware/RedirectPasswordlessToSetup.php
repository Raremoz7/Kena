<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conta leve (guest checkout / transferência) não tem senha — o middleware
 * RequirePassword da tela de segurança entraria em looping (confirma contra
 * hash nulo). Redireciona para a página de definir senha, que fecha o ciclo.
 */
class RedirectPasswordlessToSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->password === null) {
            return redirect()->route('password.setup');
        }

        return $next($request);
    }
}
