<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\PanelUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** "Esqueci minha senha" do painel (organizador/staff), broker próprio. */
class PasswordResetController extends Controller
{
    public function request(): Response
    {
        return Inertia::render('painel/forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Resposta igual exista ou não a conta: não revela quem tem acesso ao painel.
        Password::broker('panel_users')->sendResetLink(['email' => mb_strtolower(trim($data['email']))]);

        return redirect()->route('painel.login')->with('status', 'Se esse e-mail tiver acesso ao painel, enviamos um link para criar uma nova senha.');
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('painel/reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('panel_users')->reset($data, function (PanelUser $user, string $password): void {
            // Quem redefine pelo link do e-mail prova que o e-mail é dele.
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'O link expirou ou não é válido. Peça um novo.']);
        }

        return redirect()->route('painel.login')->with('status', 'Senha alterada. Entre com a nova senha.');
    }
}
