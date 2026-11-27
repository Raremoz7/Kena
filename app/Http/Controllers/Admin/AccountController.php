<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Minha conta no painel (organizador ou staff): nome e senha. */
class AccountController extends Controller
{
    public function show(): Response
    {
        $user = $this->user();

        return Inertia::render('admin/account', [
            'account' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->user()->update(['name' => $data['name']]);

        return back()->with('status', 'Nome atualizado.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:painel'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], ['current_password.current_password' => 'A senha atual não confere.']);

        $this->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('status', 'Senha alterada.');
    }

    private function user(): PanelUser
    {
        $user = Auth::guard('painel')->user();
        abort_unless($user instanceof PanelUser, 403);

        return $user;
    }
}
