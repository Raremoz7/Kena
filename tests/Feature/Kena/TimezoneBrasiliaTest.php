<?php

namespace Tests\Feature\Kena;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** O app roda no horário de Brasília: o que o painel mostra é o que acontece. */
class TimezoneBrasiliaTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_app_runs_in_brasilia_time(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('-03:00', now()->format('P'));
    }

    public function test_session_typed_at_16h_is_16h_brasilia_everywhere(): void
    {
        $session = $this->makeSession(1);
        $session->update(['starts_at' => '2026-11-20 16:00:00']);
        $session->refresh();

        $this->assertSame('16h00', CatalogPresenter::timeLabel($session->starts_at));
        // Instante real: 16:00 em Brasília = 19:00 UTC (calendário .ics, prazos).
        $this->assertSame('2026-11-20 19:00:00', $session->starts_at->copy()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_ticket_calendar_shows_the_real_time(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        $session = $this->makeSession(1);
        $session->update(['starts_at' => now()->addDays(10)->setTime(16, 0)]);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));
        app(PaymentService::class)->pay($reservation, ['method' => 'card', 'token' => 'tok']);
        $ticket = Ticket::firstOrFail();

        $ics = $this->actingAs($user)->get(route('tickets.calendar', $ticket))->assertOk()->getContent();

        // 16:00 de Brasília vira 19:00Z — antes saía 16:00Z (13:00 no celular).
        $this->assertStringContainsString('DTSTART:'.$session->fresh()->starts_at->format('Ymd').'T190000Z', (string) $ics);
    }

    public function test_sales_close_at_the_real_start_time(): void
    {
        $session = $this->makeSession(1);
        Carbon::setTestNow(Carbon::parse('2026-11-20 15:59:00', 'America/Sao_Paulo'));
        $session->update(['starts_at' => '2026-11-20 16:00:00']);

        $this->assertTrue($session->fresh()->isSellable(), 'às 15:59 de Brasília ainda vende');

        Carbon::setTestNow(Carbon::parse('2026-11-20 16:01:00', 'America/Sao_Paulo'));
        $this->assertFalse($session->fresh()->isSellable());
        Carbon::setTestNow();
    }

    public function test_courtesy_deadline_ends_at_midnight_brasilia(): void
    {
        $coupon = Coupon::create([
            'code' => 'QN-PRAZO', 'type' => Coupon::TYPE_TICKETS, 'value' => 2, 'max_uses' => 1, 'used' => 0,
            'active' => true, 'expires_at' => Carbon::parse('2026-11-10')->endOfDay(),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-10 23:30:00', 'America/Sao_Paulo'));
        $this->assertTrue($coupon->fresh()->isRedeemable());
        Carbon::setTestNow(Carbon::parse('2026-11-11 00:01:00', 'America/Sao_Paulo'));
        $this->assertFalse($coupon->fresh()->isRedeemable());
        Carbon::setTestNow();
    }

    public function test_migration_moves_system_dates_back_3h_and_keeps_typed_ones(): void
    {
        $session = $this->makeSession(1);
        $session->update(['starts_at' => '2026-11-20 16:00:00']);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'session_id' => $session->id,
            'reference' => 'KNA-ORDER-TZ', 'subtotal_cents' => 0, 'discount_cents' => 0,
            'fee_cents' => 0, 'total_cents' => 0, 'status' => Order::STATUS_PAID,
        ]);
        // Como estava gravado antes: instante em UTC.
        DB::table('orders')->where('id', $order->id)->update(['paid_at' => '2026-10-25 01:50:00']);

        $migration = require database_path('migrations/2026_10_25_000003_convert_system_timestamps_to_brasilia.php');
        $migration->up();

        $this->assertSame('2026-10-24 22:50:00', DB::table('orders')->where('id', $order->id)->value('paid_at'));
        $this->assertSame('2026-11-20 16:00:00', DB::table('event_sessions')->where('id', $session->id)->value('starts_at'));

        $migration->down();
        $this->assertSame('2026-10-25 01:50:00', DB::table('orders')->where('id', $order->id)->value('paid_at'));
    }
}
