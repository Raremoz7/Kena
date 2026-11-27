<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Models\MercadoPagoAccount;
use App\Models\PanelUser;
use App\Services\Payments\MercadoPagoOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Conectar com Mercado Pago" no painel. Só existe com KENA_MP_OAUTH_ENABLED
 * ligado (senão 404). Token e refresh_token ficam só no servidor.
 */
class MercadoPagoConnectController extends Controller
{
    private const SESSION_KEY = 'mp_oauth_attempt';

    public function __construct(private readonly MercadoPagoOAuth $oauth) {}

    public function connect(Request $request): Response
    {
        $this->ensureEnabled();
        if ($this->blockedByImpersonation($request)) {
            return $this->back('error', 'No "entrar como" não dá para conectar o Mercado Pago: só o organizador pode fazer isso.');
        }

        if (! MercadoPagoOAuth::configured()) {
            return $this->back('error', 'Faltam o Client ID e o Client Secret da aplicação do Mercado Pago no servidor.');
        }

        $attempt = $this->oauth->newAttempt();
        $request->session()->put(self::SESSION_KEY, $attempt);

        // Página externa: Inertia::location força o navegador a sair do SPA.
        return Inertia::location($this->oauth->authorizationUrl($attempt['state'], $attempt['verifier']));
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        if ($this->blockedByImpersonation($request)) {
            return $this->back('error', 'No "entrar como" não dá para conectar o Mercado Pago: só o organizador pode fazer isso.');
        }

        /** @var array{state: string, verifier: string|null}|null $attempt */
        $attempt = $request->session()->pull(self::SESSION_KEY);
        $state = (string) $request->query('state', '');

        // State diferente do gerado aqui = resposta que não foi pedida por este navegador.
        if ($attempt === null || $state === '' || ! hash_equals($attempt['state'], $state)) {
            return $this->back('error', 'A conexão expirou ou não foi iniciada aqui. Clique em Conectar de novo.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            // O organizador negou a autorização (ou o MP devolveu erro).
            return $this->back('error', 'A autorização no Mercado Pago foi cancelada.');
        }

        try {
            $user = Auth::guard('painel')->user();
            $account = $this->oauth->connect($code, $attempt['verifier'], $user instanceof PanelUser ? $user->id : null);
        } catch (PaymentException $e) {
            return $this->back('error', $e->getMessage());
        }

        return $this->back('success', 'Conta do Mercado Pago conectada'.($account->nickname ? ": {$account->nickname}." : '.'));
    }

    /**
     * Para de receber vendas nesta conta. Os tokens ficam guardados para
     * consultar e estornar os pagamentos que ela já recebeu.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        if ($this->blockedByImpersonation($request)) {
            return $this->back('error', 'No "entrar como" não dá para desconectar o Mercado Pago: só o organizador pode fazer isso.');
        }

        MercadoPagoAccount::connected()->update(['disconnected_at' => now()]);

        return $this->back('success', 'Conta do Mercado Pago desconectada. Novas vendas ficam bloqueadas até conectar outra.');
    }

    private function ensureEnabled(): void
    {
        abort_unless(MercadoPagoOAuth::enabled(), 404);
    }

    /**
     * Super admin no "entrar como" não conecta nem desconecta a conta do
     * organizador: isso é decisão do dono, e a conta seria a do admin.
     */
    private function blockedByImpersonation(Request $request): bool
    {
        return $request->session()->has(ImpersonationController::SESSION_KEY);
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect()->route('admin.settings')->with('mp_oauth', ['type' => $type, 'message' => $message]);
    }
}
