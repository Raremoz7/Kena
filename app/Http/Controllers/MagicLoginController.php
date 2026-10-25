<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MagicLoginController extends Controller
{
    /**
     * Abrir o link só mostra o botão "Entrar": antivírus de e-mail (Outlook,
     * filtros de empresa) abrem os links antes da pessoa e gastavam o token de
     * uso único. O token só é consumido no POST do botão. A assinatura/expiração
     * da URL é garantida pelo middleware `signed` nas duas rotas.
     */
    public function show(Request $request, User $user): Response|RedirectResponse
    {
        if (Auth::guard('web')->id() === $user->id) {
            return $this->continue($request);
        }

        if (($blocked = $this->blocked($user)) !== null) {
            return $blocked;
        }

        if (! MagicLink::isValid($user, (string) $request->query('token', ''))) {
            return $this->expired();
        }

        return Inertia::render('auth/magic-login', [
            'firstName' => strtok((string) $user->name, ' ') ?: null,
            'confirmUrl' => $request->fullUrl(),
        ]);
    }

    /** Botão "Entrar": consome o token (uso único) e loga. */
    public function login(Request $request, User $user): RedirectResponse
    {
        if (($blocked = $this->blocked($user)) !== null) {
            return $blocked;
        }

        if (! MagicLink::consume($user, (string) $request->query('token', ''))) {
            return $this->expired();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return $this->continue($request);
    }

    /**
     * Conta com senha ou Google entra pelo login normal (com a 2FA, se tiver):
     * um e-mail encaminhado não pode virar acesso à conta. Vale também para
     * links antigos, enviados antes desta regra.
     */
    private function blocked(User $user): ?RedirectResponse
    {
        if ($user->password === null && $user->google_id === null) {
            return null;
        }

        return redirect()->route('login')->with(
            'status',
            $user->google_id !== null && $user->password === null
                ? 'Entre com o botão “Continuar com Google” para ver seus ingressos.'
                : 'Entre com seu e-mail e senha para ver seus ingressos.',
        );
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('login')->with(
            'status',
            'Este link de acesso expirou ou já foi utilizado. Use "Esqueci minha senha" para definir uma senha de acesso.',
        );
    }

    /** Link de retomada de compra traz o caminho de volta (assinado na URL). */
    private function continue(Request $request): RedirectResponse
    {
        $redirect = MagicLink::safeRedirect($request->query('redirect'));

        return $redirect !== null ? redirect($redirect) : redirect()->route('tickets.index');
    }
}
