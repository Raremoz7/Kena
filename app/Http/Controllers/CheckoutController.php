<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\CouponService;
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
        $reservation->loadMissing('session.event');

        if ($reservation->user_id !== Auth::id()) {
            abort(403);
        }
        if (! $reservation->isActive()) {
            return redirect()->route('events.show', $reservation->session->event->slug);
        }

        $quote = $this->pricing->quoteForReservation($reservation);

        return Inertia::render('buyer/checkout', [
            'reservation' => CheckoutPresenter::reservation($reservation),
            'priceSummary' => ['lines' => $quote['lines'], 'total' => $quote['total']],
            'couponUrl' => route('checkout.coupon', $reservation),
            'payUrl' => route('checkout.pay', $reservation),
            'statusUrl' => route('checkout.status', $reservation),
            'mpPublicKey' => MercadoPagoSettings::publicKey(),
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

        $data = $request->validate([
            'method' => ['required', Rule::in(['card', 'pix'])],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'token' => ['required_if:method,card', 'string'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'payment_method_id' => ['nullable', 'string'],
        ]);

        try {
            $order = $this->payments->pay($reservation, $data);
        } catch (PaymentException) {
            return response()->json([
                'message' => 'Não foi possível processar o pagamento. Confira os dados e tente novamente.',
            ], 422);
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
        $order->loadMissing('payment');
        $payment = $order->payment;

        $pix = null;
        if ($payment !== null && $payment->method === 'pix' && $order->status === Order::STATUS_PENDING) {
            $pix = [
                'qrBase64' => $payment->pix_qr_base64,
                'copyPaste' => $payment->pix_copy_paste,
                'expiresAt' => $payment->pix_expires_at?->toIso8601String(),
            ];
        }

        return [
            'status' => $order->status, // pending | paid | failed
            'orderReference' => $order->reference,
            'redirect' => $order->status === Order::STATUS_PAID ? route('tickets.index') : null,
            'pix' => $pix,
        ];
    }

    private function ensureOwnerActive(Reservation $reservation): void
    {
        $reservation->loadMissing('session');
        abort_unless($reservation->user_id === Auth::id(), 403);
        abort_unless($reservation->isActive(), 409, 'Reserva expirada.');
    }
}
