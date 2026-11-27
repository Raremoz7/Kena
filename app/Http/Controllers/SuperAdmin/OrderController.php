<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin: busca de pedidos em todos os organizadores (suporte). Só
 * leitura: estorno e alterações continuam no painel do organizador (ou pelo
 * "entrar como").
 */
class OrderController extends Controller
{
    private const PER_PAGE = 25;

    private const STATUSES = [Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_FAILED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'organizacao' => ['nullable', 'integer'],
        ]);
        $q = trim((string) ($filters['q'] ?? ''));
        $digits = (string) preg_replace('/\D/', '', $q);

        $orders = Order::query()
            ->with(['organization', 'user', 'session.event', 'photoGallery.event'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('reference', 'like', "%{$q}%")
                ->orWhereIn('user_id', User::query()
                    ->where('email', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->when(strlen($digits) >= 6, fn ($u) => $u->orWhere('cpf', 'like', "%{$digits}%"))
                    ->select('id'))))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['organizacao'] ?? null), fn ($query) => $query->where('organization_id', (int) $filters['organizacao']))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rows = $orders->getCollection()->map(fn (Order $order): array => [
            'id' => $order->id,
            'reference' => $order->reference,
            'createdAt' => $order->created_at?->format('d/m/Y H:i'),
            'kind' => $order->isPhotos() ? 'Fotos' : 'Ingressos',
            'status' => $order->status,
            'total' => Money::toReais($order->total_cents),
            'fee' => Money::toReais($order->fee_cents),
            'organization' => $order->organization?->name,
            'event' => $order->event()?->title,
            'buyer' => ['name' => $order->user->name ?? '', 'email' => $order->user->email ?? ''],
        ]);

        return Inertia::render('super/orders', [
            'orders' => [
                'data' => $rows->values(),
                'meta' => ['currentPage' => $orders->currentPage(), 'lastPage' => $orders->lastPage(), 'total' => $orders->total()],
                'links' => ['prev' => $orders->previousPageUrl(), 'next' => $orders->nextPageUrl()],
            ],
            'filters' => ['q' => $q, 'status' => $filters['status'] ?? null, 'organizacao' => isset($filters['organizacao']) ? (int) $filters['organizacao'] : null],
            'statuses' => self::STATUSES,
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
