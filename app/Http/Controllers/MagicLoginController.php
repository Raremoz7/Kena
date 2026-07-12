<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLoginController extends Controller
{
    /**
     * Login sem senha via magic-link. A assinatura/expiração da URL é garantida
     * pelo middleware `signed`; o token de uso único é consumido aqui — link
     * clicado (ou rotacionado por um e-mail mais novo) não loga de novo.
     */
    public function login(Request $request, User $user): RedirectResponse
    {
        if (! MagicLink::consume($user, (string) $request->query('token', ''))) {
            return redirect()->route('login')->with(
                'status',
                'Este link de acesso expirou ou já foi utilizado. Use "Esqueci minha senha" para definir uma senha de acesso.',
            );
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('tickets.index');
    }
}
