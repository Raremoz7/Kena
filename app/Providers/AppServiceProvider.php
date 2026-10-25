<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Setting;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentGateway;
use App\Support\MailSettings;
use App\Support\MailUsage;
use App\Support\SessionOptionsCache;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimits();
        $this->overrideMercadoPagoConfig();
        $this->invalidateSessionOptionsCache();
        MailSettings::apply();
        // Conta os e-mails do dia (cota do SMTP principal) — ver MailUsage.
        Events::listen(MessageSent::class, fn () => MailUsage::recordSent());
    }

    /**
     * O seletor de sessões do painel de pedidos é cacheado, e seu label combina
     * título do evento + horário da sessão. Invalidar na escrita (em vez de
     * chamar forget() em cada controller) cobre também os caminhos futuros.
     */
    protected function invalidateSessionOptionsCache(): void
    {
        $forget = static function (): void {
            SessionOptionsCache::forget();
        };

        EventSession::saved($forget);
        EventSession::deleted($forget);

        // Event::deleted é necessário à parte: apagar o evento remove as sessões
        // por cascade no banco, o que não dispara EventSession::deleted.
        Event::saved($forget);
        Event::deleted($forget);
    }

    /**
     * Sobrescreve as credenciais do Mercado Pago (config/kena) com os valores
     * salvos no painel (settings encriptados), quando presentes. O .env continua
     * sendo o fallback.
     */
    protected function overrideMercadoPagoConfig(): void
    {
        try {
            // hasTable() também toca o banco: no boot sem banco (ex.:
            // package:discover antes das migrações) ele lança — mantém o
            // fallback do .env em vez de derrubar a aplicação.
            if (! Schema::hasTable('settings')) {
                return;
            }

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

        // Padrão comum: 8 caracteres com letra e número, sem exigir símbolo nem maiúscula.
        // Em produção também recusa senhas que já vazaram (ex.: "senha123").
        Password::defaults(function (): Password {
            $rule = Password::min(8)->letters()->numbers();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /** Texto do limite estourado (o padrão do Laravel é "Too Many Attempts."). */
    public const RATE_LIMIT_MESSAGE = 'Muitas tentativas seguidas. Aguarde um minuto e tente de novo.';

    /**
     * Limites contados por PESSOA, não por IP: no celular a operadora põe muita
     * gente atrás do mesmo IP (CGNAT), e o Wi-Fi da escola também. Por IP, pais
     * diferentes se bloqueavam na abertura das vendas. O teto por IP fica alto,
     * só para segurar abuso.
     */
    private function configureRateLimits(): void
    {
        // Quem é: conta logada > e-mail do convidado > sessão do navegador.
        $person = function (Request $request): string {
            if ($request->user() !== null) {
                return 'user:'.$request->user()->getAuthIdentifier();
            }

            $email = $request->input('guest.email') ?? $request->input('email');
            if (is_string($email) && trim($email) !== '') {
                return 'email:'.hash('sha256', mb_strtolower(trim($email)));
            }

            return 'session:'.$request->session()->getId();
        };

        $tooMany = fn (Request $request) => $request->expectsJson()
            ? response()->json(['message' => self::RATE_LIMIT_MESSAGE], 429)
            : back()->withErrors(['seats' => self::RATE_LIMIT_MESSAGE]);

        RateLimiter::for('reserve', fn (Request $request): array => [
            Limit::perMinute(10)->by('reserve:'.$person($request))->response($tooMany),
            Limit::perMinute(300)->by('reserve-ip:'.$request->ip())->response($tooMany),
        ]);

        // O limite por e-mail (3 envios a cada 10 min) já fica no EmailVerificationCodeService.
        RateLimiter::for('email-code', fn (Request $request): array => [
            Limit::perMinute(6)->by('email-code:'.$person($request))->response($tooMany),
            Limit::perMinute(300)->by('email-code-ip:'.$request->ip())->response($tooMany),
        ]);
    }
}
