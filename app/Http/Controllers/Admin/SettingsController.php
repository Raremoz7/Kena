<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TicketsIssuedMail;
use App\Models\MercadoPagoAccount;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Payments\MercadoPagoDiagnostic;
use App\Services\Payments\MercadoPagoOAuth;
use App\Support\CurrentOrganization;
use App\Support\GoogleWalletSettings;
use App\Support\MailSettings;
use App\Support\MailUsage;
use App\Support\MercadoPagoSettings;
use App\Support\SampleTicketOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** Configurações da organização (painel do organizador). */
    public function show(): Response
    {
        return $this->render(platform: false);
    }

    /** Configurações da plataforma (super admin, /admin/config). */
    public function showPlatform(): Response
    {
        return $this->render(platform: true);
    }

    private function render(bool $platform): Response
    {
        return Inertia::render($platform ? 'super/settings' : 'admin/settings', [
            // organization: nome, Mercado Pago (OAuth), fatura e Pix da organização.
            // platform: SMTP, Wallet, webhook, credenciais globais e padrões.
            'scope' => $platform ? 'platform' : 'organization',
            // Nome público do organizador (aparece no evento, na página dele e no e-mail).
            'organizationName' => $platform ? '' : (string) Organization::query()->whereKey(CurrentOrganization::id() ?? Organization::DEFAULT_ID)->value('name'),
            // Contato público para dúvidas e reembolsos (evento e e-mail dos ingressos).
            'organizationEmail' => $platform ? '' : (string) Organization::query()->whereKey(CurrentOrganization::id() ?? Organization::DEFAULT_ID)->value('contact_email'),
            'mp' => [
                // Valores efetivos. Segredos nunca saem do servidor.
                'publicKey' => $platform ? (string) MercadoPagoSettings::publicKey() : '',
                'accessTokenConfigured' => filled(MercadoPagoSettings::accessToken()),
                'webhookSecretConfigured' => filled(MercadoPagoSettings::webhookSecret()),
                'statementDescriptor' => MercadoPagoSettings::statementDescriptor(),
                'pixExpiration' => MercadoPagoSettings::pixExpirationMinutes(),
            ],
            // Configuração da plataforma: só sai do servidor para o super admin.
            'mail' => $platform ? MailSettings::display() : self::blankMail(),
            'mailBackup' => $platform ? MailSettings::backupDisplay() : self::blankMailBackup(),
            'mailUsage' => $platform ? MailUsage::summary() : ['sentToday' => 0, 'dailyLimit' => 0, 'warn' => false, 'over' => false],
            'gw' => $platform ? [
                'issuerId' => (string) GoogleWalletSettings::issuerId(),
                'classId' => (string) GoogleWalletSettings::classId(),
                'saEmail' => (string) GoogleWalletSettings::serviceAccountEmail(),
                'privateKeyConfigured' => filled(GoogleWalletSettings::privateKey()),
                'configured' => GoogleWalletSettings::isConfigured(),
            ] : ['issuerId' => '', 'classId' => '', 'saEmail' => '', 'privateKeyConfigured' => false, 'configured' => false],
            'mpOauth' => $this->oauthDisplay($platform),
            'webhookUrl' => $platform ? route('webhooks.mercadopago') : '',
            'saveUrl' => $platform ? route('super.settings.update') : route('admin.settings.update'),
            'testMailUrl' => $platform ? route('super.settings.test-mail') : '',
            'testMpUrl' => $platform ? route('super.settings.test-mp') : route('admin.settings.test-mp'),
            'setup' => [
                'mpAccessToken' => filled(MercadoPagoSettings::accessToken()),
                'mpPublicKey' => filled(MercadoPagoSettings::publicKey()),
                'mpWebhookSecret' => filled(MercadoPagoSettings::webhookSecret()),
                'mail' => MailSettings::isConfigured(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private static function blankMail(): array
    {
        return ['mailer' => '', 'host' => '', 'port' => 0, 'username' => '', 'encryption' => '', 'fromAddress' => '', 'fromName' => '', 'passwordConfigured' => false];
    }

    /** @return array<string, mixed> */
    private static function blankMailBackup(): array
    {
        return ['configured' => false, 'host' => '', 'port' => 0, 'username' => '', 'encryption' => '', 'passwordConfigured' => false];
    }

    /** Organização: nome público, nome na fatura e prazo do Pix. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_name' => ['nullable', 'string', 'max:120'],
            'organization_email' => ['nullable', 'email', 'max:255'],
            'mp_statement_descriptor' => ['nullable', 'string', 'max:60'],
            'mp_pix_expiration' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $organization = Organization::findOrFail(CurrentOrganization::id() ?? Organization::DEFAULT_ID);
        $organization->update([
            'name' => filled($data['organization_name'] ?? null) ? $data['organization_name'] : $organization->name,
            'contact_email' => filled($data['organization_email'] ?? null) ? mb_strtolower(trim($data['organization_email'])) : null,
            'statement_descriptor' => filled($data['mp_statement_descriptor'] ?? null) ? $data['mp_statement_descriptor'] : null,
            'pix_expiration_minutes' => filled($data['mp_pix_expiration'] ?? null) ? (int) $data['mp_pix_expiration'] : $organization->pix_expiration_minutes,
        ]);

        return back();
    }

    /** Plataforma (super admin): SMTP, Wallet, webhook, credenciais globais e padrões. */
    public function updatePlatform(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mp_access_token' => ['nullable', 'string', 'max:255'],
            'mp_public_key' => ['nullable', 'string', 'max:255'],
            'mp_webhook_secret' => ['nullable', 'string', 'max:255'],
            'mp_statement_descriptor' => ['nullable', 'string', 'max:60'],
            'mp_pix_expiration' => ['nullable', 'integer', 'min:1', 'max:1440'],

            'mail_mailer' => ['nullable', 'string', 'max:30'],
            'mail_host' => ['nullable', 'string', 'max:120'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:180'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:120'],
            'mail_from_name' => ['nullable', 'string', 'max:80'],
            'mail_daily_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],

            // SMTP secundário: usado se o principal recusar (ex.: cota do dia acabou).
            'mail_backup_host' => ['nullable', 'string', 'max:120'],
            'mail_backup_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_backup_username' => ['nullable', 'string', 'max:180'],
            'mail_backup_password' => ['nullable', 'string', 'max:255'],
            'mail_backup_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],

            'gw_issuer_id' => ['nullable', 'string', 'max:60'],
            'gw_class_id' => ['nullable', 'string', 'max:120'],
            'gw_sa_email' => ['nullable', 'email', 'max:180'],
            'gw_private_key' => ['nullable', 'string', 'max:8000'],
        ]);

        // Com OAuth ligado, token e Public Key vêm só da conta conectada: o painel não grava.
        $oauth = MercadoPagoOAuth::enabled();

        // Segredos: só sobrescreve quando um novo valor é enviado (em branco = manter).
        $secrets = ['mp_webhook_secret', 'mail_password', 'mail_backup_password', 'gw_private_key'];
        if (! $oauth) {
            $secrets[] = 'mp_access_token';
        }
        foreach ($secrets as $secret) {
            if (filled($data[$secret] ?? null)) {
                Setting::put($secret, $data[$secret]);
            }
        }

        // MP — demais campos (permitem editar/limpar).
        if (! $oauth) {
            Setting::put('mp_public_key', $data['mp_public_key'] ?? null);
        }
        // Padrões da plataforma (cada organização pode ter os seus).
        Setting::put('mp_statement_descriptor', $data['mp_statement_descriptor'] ?? null);
        if (filled($data['mp_pix_expiration'] ?? null)) {
            Setting::put('mp_pix_expiration', (string) $data['mp_pix_expiration']);
        }

        // E-mail (SMTP).
        foreach (['mail_mailer', 'mail_host', 'mail_username', 'mail_encryption', 'mail_from_address', 'mail_from_name'] as $key) {
            Setting::put($key, $data[$key] ?? null);
        }
        if (filled($data['mail_port'] ?? null)) {
            Setting::put('mail_port', (string) $data['mail_port']);
        }
        if (filled($data['mail_daily_limit'] ?? null)) {
            Setting::put('mail_daily_limit', (string) $data['mail_daily_limit']);
        }

        // SMTP secundário. Servidor em branco = desligado (a senha some junto).
        foreach (['mail_backup_host', 'mail_backup_username', 'mail_backup_encryption'] as $key) {
            Setting::put($key, $data[$key] ?? null);
        }
        Setting::put('mail_backup_port', filled($data['mail_backup_port'] ?? null) ? (string) $data['mail_backup_port'] : null);
        if (blank($data['mail_backup_host'] ?? null)) {
            Setting::put('mail_backup_password', null);
        }

        // Google Wallet (não-secretos).
        foreach (['gw_issuer_id', 'gw_class_id', 'gw_sa_email'] as $key) {
            Setting::put($key, $data[$key] ?? null);
        }

        return back();
    }

    /**
     * Estado do "Conectar com Mercado Pago" para a tela. Nunca inclui tokens.
     *
     * @return array<string, mixed>
     */
    private function oauthDisplay(bool $platform): array
    {
        if (! MercadoPagoOAuth::enabled()) {
            return ['enabled' => false];
        }

        // Na plataforma não há "conta conectada": cada organizador conecta a sua.
        $account = $platform ? null : MercadoPagoAccount::current();

        return [
            'enabled' => true,
            'configured' => MercadoPagoOAuth::configured(),
            'connectUrl' => route('admin.settings.mp.connect'),
            'disconnectUrl' => route('admin.settings.mp.disconnect'),
            'flash' => session('mp_oauth'),
            'account' => $account === null ? null : [
                'nickname' => $account->nickname,
                'mpUserId' => $account->mp_user_id,
                'liveMode' => $account->live_mode,
                'connectedAt' => $account->connected_at->toIso8601String(),
                'expiresAt' => $account->expires_at?->toIso8601String(),
            ],
        ];
    }

    /** Diagnostico da integracao com o Mercado Pago — so leitura, sem cobranca. */
    public function testMercadoPago(MercadoPagoDiagnostic $diagnostic): JsonResponse
    {
        return response()->json($diagnostic->run());
    }

    /** Envia um e-mail de teste (síncrono) para o próprio organizador, com a config salva. */
    public function testMail(): JsonResponse
    {
        MailSettings::apply();

        // Só no /admin: vai para o e-mail do super admin logado.
        $user = Auth::guard('admin')->user();
        $to = (string) $user->email;

        // Mesmo e-mail que o comprador recebe, com ingressos de exemplo (nada é salvo):
        // testa o SMTP e mostra o design real. Síncrono, para o erro voltar aqui.
        try {
            Mail::to($to)->sendNow(new TicketsIssuedMail(SampleTicketOrder::make((string) $user->name), preview: true));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Falha ao enviar: '.$e->getMessage()], 422);
        }

        return response()->json(['message' => "E-mail de teste enviado para {$to}."]);
    }
}
