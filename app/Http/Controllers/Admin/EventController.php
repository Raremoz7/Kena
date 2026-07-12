<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Sector;
use App\Models\SessionSeat;
use App\Models\Venue;
use App\Services\SessionSeatGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly SessionSeatGenerator $generator) {}

    public function create(): Response
    {
        return Inertia::render('admin/event-form', [
            'venues' => $this->venues(),
            'event' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request): void {
            $event = Event::create([
                'venue_id' => $data['venue_id'],
                'slug' => $this->uniqueSlug($data['title']),
                'title' => $data['title'],
                'kicker' => $data['kicker'],
                'description' => $data['description'],
                'status' => $data['status'],
                'duration_label' => $data['duration_label'] ?? null,
                'banner_from' => $data['banner_from'],
                'banner_to' => $data['banner_to'],
                'banner_image' => $this->storeBanner($request),
            ]);

            $sector = Sector::create([
                'event_id' => $event->id,
                'name' => $data['sector_name'],
                'price_cents' => (int) round($data['price'] * 100),
                'position' => 0,
            ]);

            /** @var array<int, array{id?: int|null, starts_at: string, doors_at?: string|null}> $sessions */
            $sessions = $data['sessions'];
            foreach ($sessions as $s) {
                $session = EventSession::create([
                    'event_id' => $event->id,
                    'starts_at' => $s['starts_at'],
                    'doors_at' => $s['doors_at'] ?? null,
                    'status' => $data['status'] === 'on_sale' ? 'on_sale' : 'scheduled',
                ]);
                $this->generator->generate($session, $sector, (int) $data['venue_id']);
            }
        });

        return redirect()->route('admin.events');
    }

    public function edit(Event $event): Response
    {
        $event->load(['sectors', 'sessions']);
        $sector = $event->sectors->first();

        return Inertia::render('admin/event-form', [
            'venues' => $this->venues(),
            'event' => [
                'id' => $event->id,
                'venue_id' => $event->venue_id,
                'title' => $event->title,
                'kicker' => $event->kicker,
                'description' => $event->description,
                'status' => $event->status,
                'duration_label' => $event->duration_label,
                'banner_from' => $event->banner_from,
                'banner_to' => $event->banner_to,
                'banner_image' => $event->banner_image,
                'sector_name' => $sector !== null ? $sector->name : 'Plateia',
                'price' => $sector ? $sector->price_cents / 100 : 0,
                'sessions' => $event->sessions->map(fn (EventSession $s): array => [
                    'id' => $s->id,
                    'starts_at' => $s->starts_at->format('Y-m-d\TH:i'),
                    'doors_at' => $s->doors_at?->format('Y-m-d\TH:i'),
                ])->values()->all(),
            ],
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $event, $request): void {
            $payload = [
                'venue_id' => $data['venue_id'],
                'title' => $data['title'],
                'kicker' => $data['kicker'],
                'description' => $data['description'],
                'status' => $data['status'],
                'duration_label' => $data['duration_label'] ?? null,
                'banner_from' => $data['banner_from'],
                'banner_to' => $data['banner_to'],
            ];
            $banner = $this->storeBanner($request);
            if ($banner !== null) {
                $payload['banner_image'] = $banner;
            }
            $event->update($payload);

            $priceCents = (int) round($data['price'] * 100);
            $sector = $event->sectors()->orderBy('position')->first()
                ?? Sector::create(['event_id' => $event->id, 'name' => $data['sector_name'], 'price_cents' => $priceCents, 'position' => 0]);
            $sector->update(['name' => $data['sector_name'], 'price_cents' => $priceCents]);

            $this->syncSessions($event, $sector, $data, $priceCents);
        });

        return redirect()->route('admin.events');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $sold = SessionSeat::whereIn('session_id', $event->sessions()->pluck('id'))
            ->where('status', SessionSeat::STATUS_SOLD)
            ->exists();

        if ($sold) {
            return back()->withErrors(['event' => 'Não é possível excluir um evento com lugares vendidos.']);
        }

        $event->delete();

        return redirect()->route('admin.events');
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'title' => ['required', 'string', 'max:120'],
            'kicker' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['draft', 'on_sale'])],
            'duration_label' => ['nullable', 'string', 'max:60'],
            'banner_from' => ['required', 'string', 'max:60'],
            'banner_to' => ['required', 'string', 'max:60'],
            'banner_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'sector_name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.id' => ['nullable', 'integer'],
            'sessions.*.starts_at' => ['required', 'date'],
            'sessions.*.doors_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * Sincroniza as sessões do evento: atualiza existentes, cria novas (gerando
     * o mapa), e remove as que sumiram (desde que sem lugares vendidos).
     *
     * @param  array<string, mixed>  $data
     */
    private function syncSessions(Event $event, Sector $sector, array $data, int $priceCents): void
    {
        /** @var array<int, array{id?: int|null, starts_at: string, doors_at?: string|null}> $incoming */
        $incoming = $data['sessions'];
        $existing = $event->sessions()->get()->keyBy('id');
        $status = $data['status'] === 'on_sale' ? 'on_sale' : 'scheduled';

        $kept = [];
        foreach ($incoming as $s) {
            $sid = isset($s['id']) ? (int) $s['id'] : null;
            $session = $sid !== null ? $existing->get($sid) : null;

            if ($session !== null) {
                $session->update(['starts_at' => $s['starts_at'], 'doors_at' => $s['doors_at'] ?? null]);
            } else {
                $session = EventSession::create([
                    'event_id' => $event->id,
                    'starts_at' => $s['starts_at'],
                    'doors_at' => $s['doors_at'] ?? null,
                    'status' => $status,
                ]);
                $this->generator->generate($session, $sector, $event->venue_id);
            }

            $kept[] = $session->id;
            SessionSeat::where('session_id', $session->id)
                ->where('sector_id', $sector->id)
                ->where('status', SessionSeat::STATUS_AVAILABLE)
                ->update(['price_cents' => $priceCents]);
        }

        foreach ($existing as $id => $session) {
            if (in_array($id, $kept, true)) {
                continue;
            }
            $hasSold = SessionSeat::where('session_id', $id)
                ->where('status', SessionSeat::STATUS_SOLD)
                ->exists();
            if (! $hasSold) {
                $session->delete();
            }
        }
    }

    /** Salva a imagem do banner (se enviada) e devolve a URL pública, ou null. */
    private function storeBanner(Request $request): ?string
    {
        if (! $request->hasFile('banner_image')) {
            return null;
        }

        $path = $request->file('banner_image')->store('banners', 'public');
        if ($path === false) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /** @return array<int, array<string, mixed>> */
    private function venues(): array
    {
        return Venue::withCount('seats')->orderBy('name')->get()->map(fn (Venue $v): array => [
            'id' => $v->id,
            'name' => $v->name,
            'city' => $v->city,
            'seats' => $v->seats_count,
        ])->all();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;
        while (Event::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
