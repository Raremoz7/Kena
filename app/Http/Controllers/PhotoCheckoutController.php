<?php

namespace App\Http\Controllers;

use App\Exceptions\CpfInUseException;
use App\Exceptions\GuestAccountExistsException;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\PhotoGallery;
use App\Models\User;
use App\Rules\EmailDomainExists;
use App\Rules\ValidCpf;
use App\Services\EmailVerificationCodeService;
use App\Services\GuestIdentityService;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use App\Services\Photos\PhotoOrderService;
use App\Support\AccountNotice;
use App\Support\MercadoPagoSettings;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Compra das fotos: o carrinho da galeria vira um pedido (preço calculado
 * aqui), pago no mesmo fluxo do Mercado Pago dos ingressos.
 */
class PhotoCheckoutController extends Controller
{
    /** Teto de fotos por pedido: protege o servidor (e o .zip) de um carrinho absurdo. */
    public const MAX_PHOTOS = 300;

    public function __construct(
        private readonly PhotoOrderService $photoOrders,
        private readonly PaymentService $payments,
        private readonly GuestIdentityService $guests,
        private readonly EmailVerificationCodeService $codes,
    ) {}

    /** "Finalizar compra" do carrinho. Aceita convidado (código por e-mail), como a reserva. */
    public function start(Request $request, string $slug): RedirectResponse
    {
        $gallery = $this->publishedGallery($slug);

        $rules = [
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['integer'],
        ];
        if (! Auth::guard('web')->check()) {
            $rules += [
                'guest.email' => ['required', 'email', 'max:255', new EmailDomainExists],
                'guest.name' => ['required', 'string', 'max:120'],
                'guest.cpf' => ['required', 'string', new ValidCpf],
                'guest.code' => ['required', 'digits:6'],
            ];
        }
        $validated = $request->validate($rules, [
            'photos.required' => 'Escolha ao menos uma foto.',
            'photos.max' => 'Cada pedido leva até '.self::MAX_PHOTOS.' fotos.',
            'guest.code.required' => 'Digite o código que enviamos para o seu e-mail.',
            'guest.code.digits' => 'O código tem 6 números.',
        ]);

        if (! Auth::guard('web')->check()) {
            if (! $this->codes->verify($validated['guest']['email'], $validated['guest']['code'])) {
                return back()->withErrors([
                    'guest.code' => 'Código inválido ou expirado. Confira o e-mail ou peça um novo código.',
                ]);
            }

            try {
                $user = $this->guests->identify($validated['guest']);
            } catch (GuestAccountExistsException) {
                return back()->withErrors([
                    'guest.email' => (AccountNotice::forEmail((string) $validated['guest']['email'])['message'] ?? 'Este e-mail já tem conta.').' Seu carrinho fica guardado.',
                ]);
            } catch (CpfInUseException) {
                return back()->withErrors([
                    'guest.cpf' => 'Esse CPF já está em outra conta. Entre com o e-mail dela para continuar.',
                ]);
            }
            Auth::login($user);
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = $request->user('web');
        $order = $this->photoOrders->createFromSelection($user, $gallery, $validated['photos']);

        return redirect()->route('photos.checkout', $order->reference);
    }

    public function show(string $reference): Response|RedirectResponse
    {
        $order = $this->ownedOrder($reference);

        if ($order->status === Order::STATUS_PAID) {
            return redirect()->route('photos.mine');
        }
        if ($order->status === Order::STATUS_REFUNDED) {
            abort(404);
        }

        $order->loadMissing(['photoItems.photo', 'payment', 'user']);
        $event = $order->event();

        return Inertia::render('buyer/photo-checkout', [
            'order' => [
                'reference' => $order->reference,
                'status' => $order->status,
                'eventTitle' => (string) $event?->title,
                'galleryUrl' => $event !== null ? route('photos.show', $event->slug) : route('photos.index'),
                'photos' => $order->photoItems->map(fn (OrderPhoto $item): array => [
                    'id' => (int) $item->photo_id,
                    'thumb' => $item->photo !== null ? '/storage/'.ltrim($item->photo->thumb_path, '/') : null,
                    'filename' => $item->filename,
                ])->values(),
                'unitPrice' => Money::toReais((int) ($order->photoItems->first()->unit_price_cents ?? 0)),
            ],
            'priceSummary' => self::priceSummary($order),
            'payUrl' => route('photos.checkout.pay', $order->reference),
            'statusUrl' => route('photos.checkout.status', $order->reference),
            'mpPublicKey' => MercadoPagoSettings::publicKey(),
            'needsDocument' => blank($order->user->cpf),
            // Pix vivo ao recarregar: restaura o QR e o polling.
            'pendingPayment' => $order->status === Order::STATUS_PENDING && $order->payment !== null
                ? $this->statusPayload($order)
                : null,
        ]);
    }

    public function pay(Request $request, string $reference): JsonResponse
    {
        $order = $this->ownedOrder($reference);
        $order->loadMissing('user');

        $requiresCpf = blank($order->user->cpf) || $request->input('method') === 'pix';
        $data = $request->validate([
            'method' => ['required', Rule::in(['card', 'pix'])],
            'token' => ['required_if:method,card', 'string'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'payment_method_id' => ['nullable', 'string'],
            'document' => [$requiresCpf ? 'required' : 'nullable', 'string', new ValidCpf],
        ]);

        try {
            $order = $this->payments->payPhotoOrder($order, $data);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->statusPayload($order));
    }

    /** Polling do Pix ou do cartão em análise. */
    public function status(string $reference): JsonResponse
    {
        return response()->json($this->statusPayload($this->ownedOrder($reference)));
    }

    /** @return array<string, mixed> */
    private function statusPayload(Order $order): array
    {
        $order->load('payment');
        $payment = $order->payment;

        $pix = null;
        if ($payment !== null && $payment->method === 'pix' && $order->status === Order::STATUS_PENDING
            && $payment->status === 'pending') {
            $pix = [
                'qrBase64' => $payment->pix_qr_base64,
                'copyPaste' => $payment->pix_copy_paste,
                'expiresAt' => $payment->pix_expires_at?->toIso8601String(),
            ];
        }

        $failureReason = null;
        if ($order->status === Order::STATUS_FAILED && is_array($payment?->payload)) {
            $detail = $payment->payload['status_detail'] ?? null;
            $failureReason = PaymentResult::friendlyStatusDetail(is_string($detail) ? $detail : null);
        }

        return [
            'status' => $order->status, // pending | paid | failed | cancelled | refunded
            'orderReference' => $order->reference,
            'redirect' => $order->status === Order::STATUS_PAID ? route('photos.mine') : null,
            'pix' => $pix,
            'failureReason' => $failureReason,
            'priceSummary' => self::priceSummary($order),
        ];
    }

    /** @return array{lines: array<int, array{label: string, value: float, tone?: string}>, total: float} */
    private static function priceSummary(Order $order): array
    {
        $order->loadMissing('photoItems');
        $count = $order->photoItems->count();
        $lines = [[
            'label' => $count.' '.($count === 1 ? 'foto' : 'fotos'),
            'value' => Money::toReais($order->subtotal_cents),
        ]];
        if ($order->discount_cents > 0) {
            $lines[] = [
                'label' => 'Desconto do pacote',
                'value' => -Money::toReais($order->discount_cents),
                'tone' => 'success',
            ];
        }

        return ['lines' => $lines, 'total' => Money::toReais($order->total_cents)];
    }

    /** Pedido de fotos da própria pessoa; de outra pessoa responde 404 (não confirma que existe). */
    private function ownedOrder(string $reference): Order
    {
        return Order::photoOrders()
            ->where('reference', $reference)
            ->where('user_id', Auth::guard('web')->id())
            ->firstOrFail();
    }

    private function publishedGallery(string $slug): PhotoGallery
    {
        return PhotoGallery::query()
            ->whereHas('event', fn ($q) => $q->where('slug', $slug))
            ->where('status', 'published')
            ->firstOrFail();
    }
}
