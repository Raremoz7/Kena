<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Login do super admin (/admin), guard `admin`, separado do painel. */
class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('super/login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('super.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        // Se estava "dentro" de um organizador, sai de lá também.
        if ($request->session()->pull(ImpersonationController::SESSION_KEY) !== null) {
            Auth::guard('painel')->logout();
        }
        $request->session()->regenerate();

        return redirect()->route('super.login');
    }
}
