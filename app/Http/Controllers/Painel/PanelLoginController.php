<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login do painel — e-mail e senha, guard `painel`. Sem Google, passkey ou
 * magic link: esses sao do comprador. O Fortify cuida do guard `web` e nao
 * encosta aqui.
 */
class PanelLoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('painel/login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('painel')->attempt($credentials, $request->boolean('remember'))) {
            // Mensagem unica: nao revela se o e-mail existe, nem se a conta
            // existe do lado do comprador.
            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('painel'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('painel')->logout();

        // Invalida so a sessao — o guard web (comprador) e independente e nao
        // deve cair junto.
        $request->session()->regenerate();

        return redirect()->route('painel.login');
    }
}
