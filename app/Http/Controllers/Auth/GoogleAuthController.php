<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->with('status', 'Não foi possível entrar com o Google. Tente novamente.');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user === null) {
            // Sem e-mail verificado pelo Google não dá para provar que é o dono.
            $raw = $googleUser instanceof AbstractUser ? $googleUser->getRaw() : [];
            if (! self::googleEmailVerified($raw) || blank($googleUser->getEmail())) {
                return redirect()->route('login')->with('status', 'Sua conta Google não tem e-mail verificado. Entre com e-mail e senha.');
            }

            $email = EmailVerificationCodeService::normalize((string) $googleUser->getEmail());
            $user = User::where('email', $email)->first();

            if ($user !== null) {
                // Só liga o Google a conta cujo e-mail o Kena confirmou e que não tem
                // senha: conta com senha pode ter sido criada por outra pessoa com
                // este e-mail — o dono liga o Google depois de entrar com a senha.
                if ($user->password !== null || $user->email_verified_at === null) {
                    return redirect()->route('login')->with('status', 'Já existe uma conta Kena com este e-mail. Entre com sua senha ou use "Esqueci minha senha".');
                }

                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?: $email,
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                ]);
                // email_verified_at não é fillable: o Google verificou o e-mail.
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * Claim do Google: `email_verified` (OpenID/userinfo v3) ou `verified_email` (v2).
     *
     * @param  array<string, mixed>  $raw
     */
    private static function googleEmailVerified(array $raw): bool
    {
        $flag = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return $flag === true || $flag === 'true';
    }
}
