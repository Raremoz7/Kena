<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Seat;
use App\Models\Sector;
use App\Models\SessionSeat;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo demo: "O Quebra-Nozes" no Teatro UNIP, com o mapa real de 500
 * assentos (app/Support/data/teatro-unip-seats.json). Todos os lugares livres.
 */
class KenaSeeder extends Seeder
{
    public function run(): void
    {
        $venue = Venue::updateOrCreate(
            ['name' => 'Teatro UNIP'],
            [
                'city' => 'Brasília',
                'state' => 'DF',
                'address' => 'SGAS I, Quadra 913, Asa Sul, Brasília - DF',
                'maps_query' => 'Teatro UNIP SGAS I Quadra 913 Asa Sul Brasília',
            ],
        );

        $event = Event::updateOrCreate(
            ['slug' => 'o-quebra-nozes'],
            [
                'venue_id' => $venue->id,
                'title' => 'O Quebra-Nozes',
                'kicker' => 'Ballet · Clássico',
                'description' => 'O clássico natalino de Tchaikovsky ganha o palco do Teatro UNIP: '
                    .'a viagem de Clara pelo Reino dos Doces, a Dança da Fada Açucarada e a batalha '
                    .'contra o Rei dos Camundongos, com orquestra ao vivo e corpo de baile completo. '
                    .'Sessão única, plateia numerada.',
                'status' => 'on_sale',
                'duration_label' => '1h50 · com intervalo',
                'banner_from' => 'oklch(0.32 0.08 285)',
                'banner_to' => 'oklch(0.14 0.012 48)',
                'banner_image' => '/img/quebra-nozes.jpg',
            ],
        );

        $sector = Sector::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Plateia'],
            ['price_cents' => 4500, 'position' => 0],
        );

        $session = EventSession::updateOrCreate(
            ['event_id' => $event->id, 'starts_at' => Carbon::create(2026, 12, 19, 20, 0)],
            ['doors_at' => Carbon::create(2026, 12, 19, 19, 0), 'status' => 'on_sale'],
        );

        $this->seedSeats($venue, $sector, $session);

        Coupon::updateOrCreate(
            ['code' => 'NOITE10'],
            ['type' => Coupon::TYPE_PERCENT, 'value' => 10, 'active' => true, 'event_id' => null],
        );
    }

    private function seedSeats(Venue $venue, Sector $sector, EventSession $session): void
    {
        $path = base_path('app/Support/data/teatro-unip-seats.json');
        /** @var array<int, array<string, mixed>> $raw */
        $raw = json_decode((string) file_get_contents($path), true) ?: [];

        $now = now();

        // 1) Assentos físicos do venue (idempotente por venue+code).
        $existing = Seat::where('venue_id', $venue->id)->pluck('id', 'code');

        $seatRows = [];
        foreach ($raw as $s) {
            $code = (string) $s['code'];
            if ($existing->has($code)) {
                continue;
            }
            $line = (string) $s['line'];
            $seatRows[] = [
                'venue_id' => $venue->id,
                'code' => $code,
                'line' => $line,
                'number' => (string) $s['number'],
                'pos_x' => (int) $s['x'],
                'pos_y' => (int) $s['y'],
                'kind' => $this->kindForLine($line, (string) ($s['kind'] ?? 'standard')),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($seatRows, 200) as $chunk) {
            DB::table('seats')->insert($chunk);
        }

        // 2) Disponibilidade da sessão (todos available).
        $seatIds = Seat::where('venue_id', $venue->id)->pluck('id', 'code');
        $taken = SessionSeat::where('session_id', $session->id)->pluck('seat_id')->flip();

        $sessionRows = [];
        foreach ($raw as $s) {
            $seatId = $seatIds[(string) $s['code']] ?? null;
            if ($seatId === null || $taken->has($seatId)) {
                continue;
            }
            $sessionRows[] = [
                'session_id' => $session->id,
                'seat_id' => $seatId,
                'sector_id' => $sector->id,
                'price_cents' => $sector->price_cents,
                'status' => SessionSeat::STATUS_AVAILABLE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($sessionRows, 200) as $chunk) {
            DB::table('session_seats')->insert($chunk);
        }
    }

    private function kindForLine(string $line, string $fallback): string
    {
        return match ($line) {
            'CAD' => 'accessible',
            'CAA' => 'companion',
            default => $fallback,
        };
    }
}
