<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MagicLoginController extends Controller
{
    /**
     * Login sem senha via URL assinada (enviada por e-mail ao convidado).
     * A validade/assinatura é garantida pelo middleware `signed`.
     */
    public function login(User $user): RedirectResponse
    {
        Auth::login($user, remember: true);

        return redirect()->route('tickets.index');
    }
}
