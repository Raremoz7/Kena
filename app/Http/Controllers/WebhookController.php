<?php

namespace App\Http\Controllers;

use App\Services\Payments\MercadoPagoSignature;
use App\Services\WebhookService;
use App\Support\MercadoPagoSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhooks) {}

    /** Recebe a notificação do Mercado Pago (assinatura verificada + idempotente). */
    public function mercadopago(Request $request): Response
    {
        $secret = MercadoPagoSettings::webhookSecret();

        // Em produção a verificação de assinatura é obrigatória: sem segredo,
        // não há como distinguir uma notificação legítima de uma forjada.
        if (blank($secret) && app()->isProduction()) {
            Log::error('Webhook do Mercado Pago recebido sem MP_WEBHOOK_SECRET configurado.');
            abort(403, 'Webhook não configurado.');
        }

        // Se há segredo configurado, rejeita notificações com assinatura inválida.
        if (filled($secret) && ! MercadoPagoSignature::verify($request, $secret)) {
            abort(401, 'Assinatura do webhook inválida.');
        }

        $this->webhooks->handleMercadoPago($request->all());

        return response()->noContent();
    }
}
