<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Support\MercadoPagoSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rede de segurança do pagamento: consulta o gateway para pagamentos pendentes
 * (pega aprovações que o webhook perdeu) e expira Pix vencido (libera assentos).
 */
class ReconcilePayments extends Command
{
    protected $signature = 'kena:reconcile-payments';

    protected $description = 'Reconcilia pagamentos pendentes com o gateway e expira Pix vencido';

    public function handle(PaymentService $payments, PaymentGateway $gateway): int
    {
        if (blank(MercadoPagoSettings::accessToken())) {
            $this->info('Sem credenciais do Mercado Pago — nada a reconciliar.');

            return self::SUCCESS;
        }

        $reconciled = $this->reconcilePending($payments, $gateway);
        $expired = $this->expireOverduePix($payments);

        $this->info("Reconciliados: {$reconciled} · Pix expirados: {$expired}");

        return self::SUCCESS;
    }

    private function reconcilePending(PaymentService $payments, PaymentGateway $gateway): int
    {
        $count = 0;
        $pending = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('gateway_payment_id')
            ->with('order')
            ->get();

        foreach ($pending as $payment) {
            try {
                $result = $gateway->fetchPayment((string) $payment->gateway_payment_id);
                $payments->sync($payment, $result);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('Reconciliação falhou', ['payment' => $payment->id, 'erro' => $e->getMessage()]);
            }
        }

        return $count;
    }

    private function expireOverduePix(PaymentService $payments): int
    {
        $count = 0;
        $overdue = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where('method', Payment::METHOD_PIX)
            ->whereNotNull('pix_expires_at')
            ->where('pix_expires_at', '<=', now())
            ->with('order')
            ->get();

        foreach ($overdue as $payment) {
            $payments->expirePending($payment);
            $count++;
        }

        return $count;
    }
}
