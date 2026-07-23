<?php

namespace App\Http\Controllers;

use App\Exceptions\CouponExhaustedException;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Reservation;
use App\Rules\ValidCpf;
use App\Services\CouponService;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Support\MercadoPagoSettings;
use App\Support\Presenters\CheckoutPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
        private readonly PaymentService $payments,
    ) {}

    public function show(Reservation $reservation): Response|RedirectResponse
    {
        $reservation->loadMissing('session.event', 'user');

        if ($reservation->user_id !== Auth::id()) {
            abort(403);
        }
        if (! $reservation->isActive()) {
            return redirect()
                ->route('events.show', $reservation->session->event->slug)
                ->with('warning', 'Sua reserva expirou e os assentos foram liberados. Escolha novamente.');
        }

        $quote = $this->pricing->quoteForReservation($reservation);

        // Refresh com Pix vivo: devolve o pagamento pendente pro front
        // restaurar o QR e o polling em vez de perder o estado.
        //
        // NÃO deferir/adiar este prop. Ele alimenta os useState iniciais de
        // buyer/checkout.tsx (método de pagamento, QR, polling e prazo do
        // countdown); adiá-lo faria a tela pintar como "cartão, sem Pix, sem
        // polling" antes de saltar para o estado pendente.
        $pendingPayment = null;
        $pendingOrder = Order::where('reservation_id', $reservation->id)
            ->where('status', Order::STATUS_PENDING)
            ->latest('id')
            ->first();
        if ($pendingOrder !== null && $pendingOrder->payment !== null) {
            $pendingPayment = $this->orderStatusPayload($pendingOrder);
        }

        return Inertia::render('buyer/checkout', [
            'reservation' => CheckoutPresenter::reservation($reservation),
            'priceSummary' => ['lines' => $quote['lines'], 'total' => $quote['total']],
            'couponUrl' => route('checkout.coupon', $reservation),
            'payUrl' => route('checkout.pay', $reservation),
            'statusUrl' => route('checkout.status', $reservation),
            'releaseUrl' => route('reservations.release', $reservation),
            'mpPublicKey' => MercadoPagoSettings::publicKey(),
            'pendingPayment' => $pendingPayment,
            // Comprador sem CPF cadastrado precisa informar no checkout —
            // o Mercado Pago exige o documento do pagador no cartão.
            'needsDocument' => blank($reservation->user->cpf),
        ]);
    }

    /** Pré-visualiza o desconto de um cupom (não persiste). */
    public function coupon(Request $request, Reservation $reservation): JsonResponse
    {
        $this->ensureOwnerActive($reservation);

        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $coupon = $this->coupons->resolve($data['code'], $reservation->session->event_id);

        if ($coupon === null) {
            return response()->json(['message' => 'Cupom inválido ou expirado.'], 422);
        }

        $quote = $this->pricing->quoteForReservation($reservation, $coupon);

        return response()->json([
            'priceSummary' => ['lines' => $quote['lines'], 'total' => $quote['total']],
            'coupon' => $coupon->code,
        ]);
    }

    /** Cria o pedido e processa o pagamento (cartão tokenizado ou Pix). */
    public function pay(Request $request, Reservation $reservation): JsonResponse
    {
        $this->ensureOwnerActive($reservation);
        $reservation->loadMissing('user');

        // CPF é obrigatório no cartão quando o comprador ainda não tem um
        // cadastrado — o Mercado Pago exige o documento do pagador.
        $requiresCpf = ($request->input('method') === 'card') && blank($reservation->user->cpf);

        $data = $request->validate([
            'method' => ['required', Rule::in(['card', 'pix'])],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'token' => ['required_if:method,card', 'string'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'payment_method_id' => ['nullable', 'string'],
            'document' => [$requiresCpf ? 'required' : 'nullable', 'string', new ValidCpf],
        ]);

        try {
            $order = $this->payments->pay($reservation, $data);
        } catch (CouponExhaustedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (PaymentException $e) {
            // A mensagem do gateway já é segura e amigável (traduzida no adapter).
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->orderStatusPayload($order));
    }

    /** Status do pedido da reserva (polling do Pix). */
    public function status(Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        $order = Order::where('reservation_id', $reservation->id)->latest('id')->first();
        if ($order === null) {
            return response()->json(['status' => 'none']);
        }

        return response()->json($this->orderStatusPayload($order));
    }

    /** @return array<string, mixed> */
    private function orderStatusPayload(Order $order): array
    {
        $order->loadMissing(['payment', 'reservation']);
        $payment = $order->payment;

        $pix = null;
        if ($payment !== null && $payment->method === 'pix' && $order->status === Order::STATUS_PENDING) {
            $pix = [
                'qrBase64' => $payment->pix_qr_base64,
                'copyPaste' => $payment->pix_copy_paste,
                'expiresAt' => $payment->pix_expires_at?->toIso8601String(),
            ];
        }

        // Motivo amigável da recusa (cartão) pro comprador saber o que corrigir.
        $failureReason = null;
        if ($order->status === Order::STATUS_FAILED && is_array($payment?->payload)) {
            $detail = $payment->payload['status_detail'] ?? null;
            $failureReason = PaymentResult::friendlyStatusDetail(is_string($detail) ? $detail : null);
        }

        return [
            'status' => $order->status, // pending | paid | failed | cancelled | refunded
            'orderReference' => $order->reference,
            'redirect' => $order->status === Order::STATUS_PAID ? route('tickets.index') : null,
            'pix' => $pix,
            'failureReason' => $failureReason,
            // Prazo REAL da reserva (o Pix estende o hold) — o countdown do front usa isso.
            'reservationExpiresAt' => $order->reservation?->expires_at?->toIso8601String(),
        ];
    }

    private function ensureOwnerActive(Reservation $reservation): void
    {
        $reservation->loadMissing('session');
        abort_unless($reservation->user_id === Auth::id(), 403);
        abort_unless($reservation->isActive(), 409, 'Reserva expirada.');
    }
}
