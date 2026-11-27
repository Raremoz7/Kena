<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string $method
 * @property string $status
 * @property int $amount_cents
 * @property string|null $gateway_payment_id
 * @property string|null $pix_qr_base64
 * @property string|null $pix_copy_paste
 * @property Carbon|null $pix_expires_at
 * @property array<string, mixed>|null $payload
 * @property int|null $mercadopago_account_id
 * @property-read Order $order
 * @property-read MercadoPagoAccount|null $mercadopagoAccount
 */
class Payment extends Model
{
    use ScopedThroughParent;

    public const METHOD_CARD = 'card';

    public const METHOD_PIX = 'pix';

    /** Pedido gratuito (cupom 100%) — emitido sem gateway. */
    public const METHOD_FREE = 'free';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pix_expires_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Conta do Mercado Pago (OAuth) que criou o pagamento.
     *
     * @return BelongsTo<MercadoPagoAccount, $this>
     */
    public function mercadopagoAccount(): BelongsTo
    {
        return $this->belongsTo(MercadoPagoAccount::class);
    }

    public function organizationParent(): string
    {
        return 'order';
    }
}
