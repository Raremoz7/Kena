<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Event;
use App\Models\Order;
use App\Support\Codes;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cortesias nominais: um código por pessoa (ex.: cada bailarina), valendo N
 * ingressos grátis até um prazo, e o relatório de quem já retirou.
 */
class CourtesyService
{
    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_EXPIRED = 'expired';

    /**
     * Lê a lista colada do painel: uma pessoa por linha, "Nome; Grupo"
     * (aceita ; ou tab — colar de planilha funciona). Grupo é opcional.
     *
     * @return array<int, array{name: string, group: string|null}>
     */
    public static function parseList(string $text): array
    {
        $people = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s*[;\t]\s*/u', $line, 2) ?: [$line];
            $name = trim((string) $parts[0]);
            $group = isset($parts[1]) ? trim((string) $parts[1]) : '';

            if ($name !== '') {
                $people[] = ['name' => mb_substr($name, 0, 120), 'group' => $group !== '' ? mb_substr($group, 0, 80) : null];
            }
        }

        return $people;
    }

    /**
     * Cria um código por pessoa.
     *
     * @param  array<int, array{name: string, group: string|null}>  $people
     * @return Collection<int, Coupon>
     */
    public function generate(Event $event, array $people, int $tickets, ?CarbonInterface $expiresAt, string $prefix): Collection
    {
        $prefix = self::normalizePrefix($prefix);

        return DB::transaction(fn (): Collection => collect($people)->map(fn (array $person): Coupon => Coupon::create([
            'event_id' => $event->id,
            'code' => $this->uniqueCode($prefix),
            'beneficiary' => $person['name'],
            'group_name' => $person['group'],
            'type' => Coupon::TYPE_TICKETS,
            'value' => $tickets,
            'max_uses' => 1,
            'used' => 0,
            'expires_at' => $expiresAt,
            'active' => true,
        ])));
    }

    /**
     * Cortesias do evento com a situação de cada uma e quem retirou.
     *
     * @return array<int, array{id: int, code: string, beneficiary: string|null, group: string|null, tickets: int, expiresAt: string|null, status: string, redeemedBy: array{name: string, email: string, phone: string|null}|null, orderReference: string|null, seats: array<int, string>, redeemedAt: string|null}>
     */
    public function report(Event $event): array
    {
        $coupons = Coupon::query()
            ->where('event_id', $event->id)
            ->where('type', Coupon::TYPE_TICKETS)
            ->orderBy('group_name')
            ->orderBy('beneficiary')
            ->get();

        // Só pedido pago conta como retirada (pendente/falho devolve o uso).
        $redemptions = CouponRedemption::query()
            ->whereIn('coupon_id', $coupons->pluck('id'))
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->with(['order.user', 'order.items'])
            ->get()
            ->keyBy('coupon_id');

        return $coupons->map(function (Coupon $coupon) use ($redemptions): array {
            /** @var CouponRedemption|null $redemption */
            $redemption = $redemptions->get($coupon->id);
            $order = $redemption?->order;

            $status = match (true) {
                $order !== null => self::STATUS_REDEEMED,
                ! $coupon->active || ($coupon->expires_at !== null && $coupon->expires_at->isPast()) => self::STATUS_EXPIRED,
                default => self::STATUS_PENDING,
            };

            return [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'beneficiary' => $coupon->beneficiary,
                'group' => $coupon->group_name,
                'tickets' => $coupon->value,
                'expiresAt' => $coupon->expires_at?->toIso8601String(),
                'status' => $status,
                'redeemedBy' => $order !== null ? [
                    'name' => (string) $order->user->name,
                    'email' => (string) $order->user->email,
                    'phone' => $order->user->phone,
                ] : null,
                'orderReference' => $order?->reference,
                'seats' => $order !== null ? $order->items->pluck('seat_code')->all() : [],
                'redeemedAt' => $order?->paid_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public static function normalizePrefix(string $prefix): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $prefix));

        return $clean !== '' ? substr($clean, 0, 8) : 'CORT';
    }

    private function uniqueCode(string $prefix): string
    {
        do {
            $code = Codes::courtesy($prefix);
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
