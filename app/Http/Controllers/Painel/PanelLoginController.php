<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\PanelUser;
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
    public function show(Request $request): Response
    {
        return Inertia::render('painel/login', [
            'signupEnabled' => OrganizerSignupController::enabled(),
            'status' => $request->session()->get('status'),
        ]);
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

        // Conta criada pelo cadastro aberto só entra depois de confirmar o e-mail.
        $user = Auth::guard('painel')->user();
        if ($user instanceof PanelUser && $user->email_verified_at === null) {
            Auth::guard('painel')->logout();
            OrganizerSignupController::sendVerification($user);

            throw ValidationException::withMessages([
                'email' => 'Confirme seu e-mail antes de entrar. Reenviamos o link para '.$user->email.'.',
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
