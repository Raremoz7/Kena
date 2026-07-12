<?php

namespace Database\Seeders;

use App\Models\EventSession;
use App\Models\SessionSeat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot exato dos dados transacionais que só existiam localmente (gerados
 * usando o app, não por seeder): os usuários reais/demo e a jornada de
 * compra completa (reserva → pedido pago → ticket) do evento "O
 * Quebra-Nozes". Extraído do database/database.sqlite local em 2026-07-12.
 *
 * O catálogo (venue/evento/setor/sessão/500 assentos/cupom) já é reproduzido
 * de forma idêntica pelo KenaSeeder — este seeder assume que ele já rodou
 * (chama-o de novo por segurança, é idempotente).
 *
 * IMPORTANTE: roda uma vez só, em produção, ANTES de qualquer cadastro real
 * de usuário — os IDs de users/reservations/orders/tickets são hardcoded
 * (insertOrIgnore) para reproduzir o estado local 1:1. Rodar depois que já
 * existirem usuários reais pode colidir com esses IDs.
 *
 *     php artisan db:seed --class=LocalSnapshotSeeder
 *
 * Contém só os 3 usuários de teste (helena@veludo.test, organizador@veludo.test,
 * preview@kena.test) — os usuários reais do Davi (davimoreira10@gmail.com,
 * davimoreira12@gmail.com) foram deixados de fora de propósito para não expor
 * e-mail/CPF real no histórico deste repositório público. Crie-os à parte em
 * produção (registro normal ou `php artisan tinker`).
 */
class LocalSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(KenaSeeder::class);

        $session = EventSession::whereHas(
            'event',
            fn ($q) => $q->where('slug', 'o-quebra-nozes'),
        )->firstOrFail();

        $seatIdByCode = $this->sessionSeatIdsByCode($session);

        DB::transaction(function () use ($session, $seatIdByCode) {
            $this->seedUsers();
            $this->seedReservations($session);
            $this->seedReservationSeats($seatIdByCode);
            $this->seedOrders($session);
            $this->seedOrderItems($seatIdByCode);
            $this->seedTickets($session, $seatIdByCode);
        });
    }

    /** @return array<string, int> código do assento (ex: "A10") => session_seat.id */
    private function sessionSeatIdsByCode(EventSession $session): array
    {
        return SessionSeat::query()
            ->where('session_id', $session->id)
            ->join('seats', 'seats.id', '=', 'session_seats.seat_id')
            ->pluck('session_seats.id', 'seats.code')
            ->all();
    }

    private function seedUsers(): void
    {
        DB::table('users')->insertOrIgnore([
            [
                'id' => 1,
                'name' => 'Helena Drummond',
                'email' => 'helena@veludo.test',
                'email_verified_at' => '2026-07-02 02:15:02',
                'password' => '$2y$12$FhTHbhdlEzjuUcArJb9hiOy.a.FW9sWnDkU6wiPLDHEXL0aTGqE5y',
                'is_admin' => false,
                'role' => 'buyer',
                'created_at' => '2026-07-02 02:15:03',
                'updated_at' => '2026-07-02 02:15:03',
            ],
            [
                'id' => 3,
                'name' => 'Produção Kena',
                'email' => 'organizador@veludo.test',
                'email_verified_at' => '2026-07-02 02:15:04',
                'password' => '$2y$12$8QjMr8PhqNAsY0lOYVNcwea1ufmxZsMnv94VgE2vuTnrhMJB0zvjO',
                'is_admin' => false,
                'role' => 'organizer',
                'created_at' => '2026-07-02 02:15:04',
                'updated_at' => '2026-07-10 08:08:51',
            ],
            [
                'id' => 4,
                'name' => 'Helena Preview',
                'email' => 'preview@kena.test',
                'email_verified_at' => null,
                'password' => null,
                'is_admin' => false,
                'role' => 'buyer',
                'created_at' => '2026-07-02 02:15:14',
                'updated_at' => '2026-07-02 02:15:14',
            ],
        ]);
    }

    private function seedReservations(EventSession $session): void
    {
        DB::table('reservations')->insertOrIgnore([
            [
                'id' => 1,
                'session_id' => $session->id,
                'user_id' => 4,
                'status' => 'converted',
                'expires_at' => '2026-07-02 02:25:55',
                'created_at' => '2026-07-02 02:15:55',
                'updated_at' => '2026-07-02 02:15:55',
            ],
            [
                'id' => 3,
                'session_id' => $session->id,
                'user_id' => 1,
                'status' => 'active',
                'expires_at' => '2026-07-08 18:27:34',
                'created_at' => '2026-07-08 18:17:34',
                'updated_at' => '2026-07-08 18:17:34',
            ],
        ]);
    }

    /** @param array<string, int> $seatIdByCode */
    private function seedReservationSeats(array $seatIdByCode): void
    {
        DB::table('reservation_seats')->insertOrIgnore([
            [
                'id' => 1,
                'reservation_id' => 1,
                'session_seat_id' => $seatIdByCode['A10'],
                'price_cents' => 4500,
                'created_at' => '2026-07-02 02:15:55',
                'updated_at' => '2026-07-02 02:15:55',
            ],
            [
                'id' => 3,
                'reservation_id' => 3,
                'session_seat_id' => $seatIdByCode['A13'],
                'price_cents' => 4500,
                'created_at' => '2026-07-08 18:17:34',
                'updated_at' => '2026-07-08 18:17:34',
            ],
        ]);
    }

    private function seedOrders(EventSession $session): void
    {
        DB::table('orders')->insertOrIgnore([
            [
                'id' => 1,
                'user_id' => 4,
                'session_id' => $session->id,
                'reservation_id' => 1,
                'coupon_id' => null,
                'reference' => 'KNA-ORDER-L7594DV9',
                'subtotal_cents' => 4500,
                'discount_cents' => 0,
                'fee_cents' => 450,
                'total_cents' => 4950,
                'status' => 'paid',
                'paid_at' => '2026-07-02 02:15:55',
                'created_at' => '2026-07-02 02:15:55',
                'updated_at' => '2026-07-02 02:15:55',
            ],
        ]);
    }

    /** @param array<string, int> $seatIdByCode */
    private function seedOrderItems(array $seatIdByCode): void
    {
        DB::table('order_items')->insertOrIgnore([
            [
                'id' => 1,
                'order_id' => 1,
                'session_seat_id' => $seatIdByCode['A10'],
                'seat_code' => 'A10',
                'sector_name' => 'Plateia',
                'price_cents' => 4500,
                'created_at' => '2026-07-02 02:15:55',
                'updated_at' => '2026-07-02 02:15:55',
            ],
        ]);
    }

    /** @param array<string, int> $seatIdByCode */
    private function seedTickets(EventSession $session, array $seatIdByCode): void
    {
        DB::table('tickets')->insertOrIgnore([
            [
                'id' => 1,
                'order_id' => 1,
                'order_item_id' => 1,
                'session_id' => $session->id,
                'user_id' => 4,
                'session_seat_id' => $seatIdByCode['A10'],
                'code' => 'KNA-H5Q4-2E7A-W688',
                'qr_token' => 'KNA-H5Q4-2E7A-W688.joRJUNK6Mn.a9a183325317c649',
                'holder_name' => 'Helena Preview',
                'seat_code' => 'A10',
                'sector_name' => 'Plateia',
                'price_cents' => 4500,
                'status' => 'valid',
                'checked_in_at' => null,
                'created_at' => '2026-07-02 02:15:55',
                'updated_at' => '2026-07-02 02:15:55',
            ],
        ]);
    }
}
