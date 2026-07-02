<?php

namespace App\Http\Controllers;

use App\Services\Payments\MercadoPagoSignature;
use App\Services\WebhookService;
use App\Support\MercadoPagoSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhooks) {}

    /** Recebe a notificação do Mercado Pago (assinatura verificada + idempotente). */
    public function mercadopago(Request $request): Response
    {
        // Se há segredo configurado, rejeita notificações com assinatura inválida.
        $secret = MercadoPagoSettings::webhookSecret();
        if (filled($secret) && ! MercadoPagoSignature::verify($request, $secret)) {
            abort(401, 'Assinatura do webhook inválida.');
        }

        $this->webhooks->handleMercadoPago($request->all());

        return response()->noContent();
    }
}
