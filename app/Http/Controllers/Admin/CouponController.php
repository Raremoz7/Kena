<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Event;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        $eventTitles = Event::pluck('title', 'id');

        $coupons = Coupon::latest('id')->get()->map(fn (Coupon $c): array => [
            'id' => $c->id,
            'code' => $c->code,
            'type' => $c->type,
            'valueLabel' => $c->type === Coupon::TYPE_PERCENT
                ? $c->value.'%'
                : 'R$ '.number_format(Money::toReais($c->value), 2, ',', '.'),
            'used' => $c->used,
            'maxUses' => $c->max_uses,
            'active' => $c->active,
            'expired' => $c->expires_at !== null && $c->expires_at->isPast(),
            'event' => $c->event_id !== null ? ($eventTitles[$c->event_id] ?? 'Todos os eventos') : 'Todos os eventos',
            'expiresAt' => $c->expires_at?->format('d/m/Y H:i'),
        ])->all();

        return Inertia::render('admin/coupons', ['coupons' => $coupons]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupon-form', [
            'coupon' => null,
            'events' => $this->eventOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::create($this->validated($request));

        return redirect()->route('admin.coupons');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupon-form', [
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                // Fixo é exibido em reais; percentual em pontos.
                'value' => $coupon->type === Coupon::TYPE_FIXED ? Money::toReais($coupon->value) : $coupon->value,
                'max_uses' => $coupon->max_uses,
                'expires_at' => $coupon->expires_at?->format('Y-m-d\TH:i'),
                'active' => $coupon->active,
                'event_id' => $coupon->event_id,
            ],
            'events' => $this->eventOptions(),
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon->id));

        return redirect()->route('admin.coupons');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'min:0', $request->input('type') === Coupon::TYPE_PERCENT ? 'max:100' : 'max:100000'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['boolean'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        // Fixo: reais → centavos. Percentual: inteiro.
        $value = $data['type'] === Coupon::TYPE_FIXED
            ? (int) round(((float) $data['value']) * 100)
            : (int) $data['value'];

        return [
            'code' => mb_strtoupper(trim($data['code'])),
            'type' => $data['type'],
            'value' => $value,
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => $data['active'] ?? true,
            'event_id' => $data['event_id'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function eventOptions(): array
    {
        return Event::orderBy('title')->get(['id', 'title'])
            ->map(fn (Event $e): array => ['id' => $e->id, 'title' => $e->title])->all();
    }
}
