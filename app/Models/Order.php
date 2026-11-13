<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kind
 * @property int $user_id
 * @property int|null $session_id
 * @property int|null $photo_gallery_id
 * @property int|null $reservation_id
 * @property int|null $coupon_id
 * @property string $reference
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $fee_cents
 * @property int $total_cents
 * @property string $status
 * @property Carbon|null $paid_at
 * @property-read EventSession|null $session
 * @property-read PhotoGallery|null $photoGallery
 * @property-read User $user
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderPhoto> $photoItems
 * @property-read Collection<int, Ticket> $tickets
 * @property-read Payment|null $payment
 */
class Order extends Model
{
    /** Pedido de ingressos (assentos de uma sessão). */
    public const KIND_TICKETS = 'tickets';

    /** Pedido de fotos do espetáculo (de uma galeria, sem sessão). */
    public const KIND_PHOTOS = 'photos';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => self::KIND_TICKETS,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return BelongsTo<PhotoGallery, $this> */
    public function photoGallery(): BelongsTo
    {
        return $this->belongsTo(PhotoGallery::class);
    }

    /** @return HasMany<OrderPhoto, $this> */
    public function photoItems(): HasMany
    {
        return $this->hasMany(OrderPhoto::class);
    }

    public function isPhotos(): bool
    {
        return $this->kind === self::KIND_PHOTOS;
    }

    /** Evento do pedido: o da sessão (ingressos) ou o da galeria (fotos). */
    public function event(): ?Event
    {
        if ($this->isPhotos()) {
            $this->loadMissing('photoGallery.event');

            return $this->photoGallery?->event;
        }

        $this->loadMissing('session.event');

        return $this->session?->event;
    }

    /** @param  Builder<Order>  $query */
    public function scopeTicketOrders(Builder $query): void
    {
        $query->where('kind', self::KIND_TICKETS);
    }

    /** @param  Builder<Order>  $query */
    public function scopePhotoOrders(Builder $query): void
    {
        $query->where('kind', self::KIND_PHOTOS);
    }
}
