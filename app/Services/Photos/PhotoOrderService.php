<?php

namespace App\Services\Photos;

use App\Mail\PhotosReadyMail;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\Payment;
use App\Models\PhotoGallery;
use App\Models\User;
use App\Services\IssueOutcome;
use App\Services\Payments\PaymentGateway;
use App\Support\Codes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Pedido de fotos: nasce do carrinho (fotos escolhidas na galeria), usa o
 * mesmo `orders`/`payments` dos ingressos e é liberado quando o pagamento é
 * aprovado. O acesso às fotos vale enquanto o pedido estiver pago: estorno
 * (pelo painel, pelo Mercado Pago ou chargeback) tira o pedido de "pago" e o
 * download para de funcionar na hora.
 */
class PhotoOrderService
{
    public function __construct(
        private readonly PhotoPricing $pricing,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Cria o pedido pendente com as fotos escolhidas. Preço recalculado aqui.
     * Um pedido pendente anterior da mesma pessoa nesta galeria é reaproveitado
     * se tiver as mesmas fotos; senão é cancelado (com o Pix dele derrubado no
     * gateway, para não ficar um QR antigo pagável).
     *
     * @param  array<int, int|string>  $photoIds
     */
    public function createFromSelection(User $user, PhotoGallery $gallery, array $photoIds): Order
    {
        $ids = array_values(array_unique(array_map('intval', $photoIds)));
        sort($ids);

        $photos = $gallery->photos()->whereIn('id', $ids)->get(['id', 'filename']);
        if ($ids === [] || $photos->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'photos' => 'Algumas fotos do carrinho não estão mais nesta galeria. Confira a seleção e tente de novo.',
            ]);
        }

        // Quem já comprou uma foto não paga por ela de novo.
        $owned = $this->ownedPhotoIds($user, $ids);
        if ($owned !== []) {
            throw ValidationException::withMessages([
                'photos' => count($owned) === 1
                    ? 'Uma das fotos do carrinho você já comprou. Ela está em Minhas fotos.'
                    : count($owned).' fotos do carrinho você já comprou. Elas estão em Minhas fotos.',
            ]);
        }

        $quote = $this->pricing->quote($gallery, count($ids));

        $stale = [];
        $order = DB::transaction(function () use ($user, $gallery, $photos, $ids, $quote, &$stale): Order {
            $pending = Order::photoOrders()
                ->where('user_id', $user->id)
                ->where('photo_gallery_id', $gallery->id)
                ->where('status', Order::STATUS_PENDING)
                ->with('photoItems')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $existing) {
                $existingIds = $existing->photoItems->pluck('photo_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
                if ($existingIds === $ids && $existing->total_cents === $quote['total_cents']) {
                    return $existing;
                }

                $existing->update(['status' => Order::STATUS_CANCELLED]);
                $stale[] = $existing->id;
            }

            $order = Order::create([
                'kind' => Order::KIND_PHOTOS,
                'user_id' => $user->id,
                'photo_gallery_id' => $gallery->id,
                'reference' => Codes::orderReference(),
                'subtotal_cents' => $quote['subtotal_cents'],
                'discount_cents' => $quote['discount_cents'],
                'fee_cents' => 0,
                'total_cents' => $quote['total_cents'],
                'status' => Order::STATUS_PENDING,
            ]);

            foreach ($photos as $photo) {
                OrderPhoto::create([
                    'order_id' => $order->id,
                    'photo_id' => $photo->id,
                    'filename' => $photo->filename,
                    'unit_price_cents' => $quote['unit_price_cents'],
                ]);
            }

            return $order;
        });

        $this->cancelLivePayments($stale);

        return $order;
    }

    /**
     * Libera as fotos de um pedido pago. Idempotente e serializado (webhook e
     * conciliação podem chegar juntos); pedido cancelado ou estornado não
     * revive com aprovação atrasada: devolve NotPayable e o PaymentService
     * estorna.
     */
    public function fulfill(Order $order): IssueOutcome
    {
        $outcome = DB::transaction(function () use ($order): IssueOutcome {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === Order::STATUS_PAID) {
                return IssueOutcome::AlreadyIssued;
            }
            if ($fresh->status !== Order::STATUS_PENDING) {
                return IssueOutcome::NotPayable;
            }

            $fresh->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

            return IssueOutcome::Issued;
        });

        if ($outcome === IssueOutcome::Issued) {
            $order->refresh()->loadMissing('user');
            if (filled($order->user->email)) {
                Mail::to($order->user->email)->queue(new PhotosReadyMail($order));
            }
        }

        return $outcome;
    }

    /**
     * Fotos que a pessoa comprou e ainda pode baixar (pedido pago).
     *
     * @param  array<int, int>|null  $among  limita a conferência a estes ids
     * @return array<int, int>
     */
    public function ownedPhotoIds(User $user, ?array $among = null): array
    {
        return OrderPhoto::query()
            ->whereNotNull('photo_id')
            ->when($among !== null, fn ($q) => $q->whereIn('photo_id', $among))
            ->whereHas('order', fn ($q) => $q->photoOrders()
                ->where('user_id', $user->id)
                ->where('status', Order::STATUS_PAID))
            ->distinct()
            ->pluck('photo_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function owns(User $user, int $photoId): bool
    {
        return $this->ownedPhotoIds($user, [$photoId]) !== [];
    }

    /** @param  array<int, int>  $orderIds */
    private function cancelLivePayments(array $orderIds): void
    {
        if ($orderIds === []) {
            return;
        }

        $live = Payment::whereIn('order_id', $orderIds)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('gateway_payment_id')
            ->get();

        foreach ($live as $payment) {
            $this->gateway->cancelPayment((string) $payment->gateway_payment_id);
            $payment->update(['status' => Payment::STATUS_CANCELLED]);
        }
    }
}
