<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Definir senha" para contas leves (guest checkout / transferência recebida),
 * que hoje só acessam por magic-link. Com senha, viram conta normal (login pela senha).
 */
class PasswordSetupController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Já tem senha → usa a troca de senha normal das configurações.
        if ($user->password !== null) {
            return redirect()->route('tickets.index');
        }

        return Inertia::render('buyer/set-password');
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // O cast 'hashed' do model faz o hash ao salvar.
        $user->update(['password' => $data['password']]);

        return redirect()->route('tickets.index');
    }
}
