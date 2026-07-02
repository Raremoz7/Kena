<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentGateway;
use App\Support\MailSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, MercadoPagoGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->overrideMercadoPagoConfig();
        MailSettings::apply();
    }

    /**
     * Sobrescreve as credenciais do Mercado Pago (config/kena) com os valores
     * salvos no painel (settings encriptados), quando presentes. O .env continua
     * sendo o fallback.
     */
    protected function overrideMercadoPagoConfig(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        try {
            $settings = Setting::map();
        } catch (\Throwable) {
            return;
        }

        $map = [
            'mp_access_token' => 'kena.mercadopago.access_token',
            'mp_public_key' => 'kena.mercadopago.public_key',
            'mp_webhook_secret' => 'kena.mercadopago.webhook_secret',
            'mp_statement_descriptor' => 'kena.mercadopago.statement_descriptor',
        ];

        foreach ($map as $key => $configKey) {
            if (! empty($settings[$key])) {
                config([$configKey => $settings[$key]]);
            }
        }

        if (! empty($settings['mp_pix_expiration'])) {
            config(['kena.mercadopago.pix_expiration_minutes' => (int) $settings['mp_pix_expiration']]);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        CarbonImmutable::setLocale('pt_BR');

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
