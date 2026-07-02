<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\GoogleWalletSettings;
use App\Support\MailSettings;
use App\Support\MercadoPagoSettings;
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
    public function show(): Response
    {
        return Inertia::render('admin/settings', [
            'mp' => [
                // Valores efetivos (painel tem prioridade; cai para .env). Segredos nunca saem do servidor.
                'publicKey' => (string) MercadoPagoSettings::publicKey(),
                'accessTokenConfigured' => filled(MercadoPagoSettings::accessToken()),
                'webhookSecretConfigured' => filled(MercadoPagoSettings::webhookSecret()),
                'statementDescriptor' => MercadoPagoSettings::statementDescriptor(),
                'pixExpiration' => MercadoPagoSettings::pixExpirationMinutes(),
            ],
            'mail' => MailSettings::display(),
            'gw' => [
                'issuerId' => (string) GoogleWalletSettings::issuerId(),
                'classId' => (string) GoogleWalletSettings::classId(),
                'saEmail' => (string) GoogleWalletSettings::serviceAccountEmail(),
                'privateKeyConfigured' => filled(GoogleWalletSettings::privateKey()),
                'configured' => GoogleWalletSettings::isConfigured(),
            ],
            'webhookUrl' => route('webhooks.mercadopago'),
            'testMailUrl' => route('admin.settings.test-mail'),
            'setup' => [
                'mpAccessToken' => filled(MercadoPagoSettings::accessToken()),
                'mpPublicKey' => filled(MercadoPagoSettings::publicKey()),
                'mpWebhookSecret' => filled(MercadoPagoSettings::webhookSecret()),
                'mail' => MailSettings::isConfigured(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
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

            'gw_issuer_id' => ['nullable', 'string', 'max:60'],
            'gw_class_id' => ['nullable', 'string', 'max:120'],
            'gw_sa_email' => ['nullable', 'email', 'max:180'],
            'gw_private_key' => ['nullable', 'string', 'max:8000'],
        ]);

        // Segredos: só sobrescreve quando um novo valor é enviado (em branco = manter).
        foreach (['mp_access_token', 'mp_webhook_secret', 'mail_password', 'gw_private_key'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                Setting::put($secret, $data[$secret]);
            }
        }

        // MP — demais campos (permitem editar/limpar).
        Setting::put('mp_public_key', $data['mp_public_key'] ?? null);
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

        // Google Wallet (não-secretos).
        foreach (['gw_issuer_id', 'gw_class_id', 'gw_sa_email'] as $key) {
            Setting::put($key, $data[$key] ?? null);
        }

        return back();
    }

    /** Envia um e-mail de teste (síncrono) para o próprio organizador, com a config salva. */
    public function testMail(): JsonResponse
    {
        MailSettings::apply();

        /** @var User $user */
        $user = Auth::user();
        $to = (string) $user->email;

        try {
            Mail::raw(
                'Este é um e-mail de teste do Kena. Se você recebeu, o SMTP está configurado corretamente.',
                fn ($message) => $message->to($to)->subject('Kena — e-mail de teste'),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Falha ao enviar: '.$e->getMessage()], 422);
        }

        return response()->json(['message' => "E-mail de teste enviado para {$to}."]);
    }
}
