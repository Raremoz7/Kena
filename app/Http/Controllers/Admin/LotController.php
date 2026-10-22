<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\TicketLot;
use App\Services\TicketLotService;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Lotes de venda de uma sessão (preço e quantidade de ingressos pagos por lote). */
class LotController extends Controller
{
    public function __construct(private readonly TicketLotService $lots) {}

    public function index(EventSession $session): Response
    {
        $session->load('event.sectors');

        return Inertia::render('admin/lots', [
            'event' => ['title' => $session->event->title, 'slug' => $session->event->slug, 'id' => $session->event->id],
            'session' => ['id' => $session->id, 'label' => CatalogPresenter::sessionLabel($session)],
            'lots' => $this->lots->summary($session),
            'sectorPriceCents' => (int) ($session->event->sectors->min('price_cents') ?? 0),
            'seatCount' => $session->sessionSeats()->count(),
        ]);
    }

    public function store(Request $request, EventSession $session): RedirectResponse
    {
        $data = $this->validated($request);

        TicketLot::create([
            'session_id' => $session->id,
            'name' => $data['name'],
            'price_cents' => $data['price_cents'],
            'quantity' => $data['quantity'],
            'position' => (int) ($session->lots()->max('position') ?? -1) + 1,
        ]);

        return back()->with('status', "Lote \"{$data['name']}\" criado.");
    }

    public function update(Request $request, TicketLot $lot): RedirectResponse
    {
        $data = $this->validated($request);
        $used = $this->lots->used($lot);

        if ($data['quantity'] !== null && $data['quantity'] < $used) {
            return back()->withErrors(['quantity' => "Esse lote já tem {$used} ingressos vendidos ou reservados."]);
        }

        $lot->update([
            'name' => $data['name'],
            'price_cents' => $data['price_cents'],
            'quantity' => $data['quantity'],
        ]);

        return back()->with('status', "Lote \"{$lot->name}\" atualizado.");
    }

    public function destroy(TicketLot $lot): RedirectResponse
    {
        if ($this->lots->used($lot) > 0) {
            return back()->with('error', 'Esse lote já tem ingressos vendidos ou reservados e não pode ser removido.');
        }

        $lot->delete();

        return back()->with('status', 'Lote removido.');
    }

    /** @return array{name: string, price_cents: int, quantity: int|null} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ], [
            'quantity.min' => 'A quantidade precisa ser de pelo menos 1 ingresso (ou deixe vazio para sem limite).',
        ]);

        return [
            'name' => (string) $data['name'],
            // Preço vem em reais (ex.: 45.50); guarda em centavos.
            'price_cents' => (int) round(((float) $data['price']) * 100),
            'quantity' => isset($data['quantity']) ? (int) $data['quantity'] : null,
        ];
    }
}
