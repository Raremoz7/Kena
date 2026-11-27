<?php

namespace App\Console\Commands;

use App\Models\MercadoPagoAccount;
use App\Services\Payments\MercadoPagoOAuth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Renova os tokens OAuth do Mercado Pago antes de vencerem (validade de 180
 * dias). Sem isso, a conta para de vender e o organizador precisa reconectar.
 */
class RefreshMercadoPagoTokens extends Command
{
    protected $signature = 'kena:refresh-mp-tokens';

    protected $description = 'Renova os tokens OAuth do Mercado Pago perto do vencimento';

    public function handle(MercadoPagoOAuth $oauth): int
    {
        if (! MercadoPagoOAuth::enabled()) {
            $this->info('OAuth do Mercado Pago desligado — nada a renovar.');

            return self::SUCCESS;
        }

        $limit = now()->addDays((int) config('kena.mercadopago.oauth.refresh_before_days', 30));
        $renewed = 0;

        MercadoPagoAccount::connected()
            ->whereNotNull('refresh_token')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $limit)
            ->each(function (MercadoPagoAccount $account) use ($oauth, &$renewed): void {
                try {
                    $oauth->refresh($account);
                    $renewed++;
                } catch (\Throwable $e) {
                    Log::error('Falha ao renovar token do Mercado Pago.', ['account' => $account->id, 'erro' => $e->getMessage()]);
                }
            });

        $this->info("Tokens renovados: {$renewed}");

        return self::SUCCESS;
    }
}
