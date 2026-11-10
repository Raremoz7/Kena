<?php

namespace Tests\Feature\Kena;

use App\Mail\EventDayReminderMail;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Lembrete "É hoje": todo titular de ingresso válido recebe, uma vez só. */
class EventDayReminderTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function issue(EventSession $session, User $buyer, int $seats = 1): Order
    {
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, $seats));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order;
    }

    public function test_sends_once_to_each_holder_on_the_event_day(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-11-20 08:00:00');

        $session = $this->makeSession(6);
        $session->update(['starts_at' => '2026-11-20 16:00:00']);

        $ana = User::factory()->create(['email' => 'ana@example.com']);
        $bia = User::factory()->create(['email' => 'bia@example.com']);
        $estornado = User::factory()->create(['email' => 'estorno@example.com']);
        $this->issue($session, $ana, 2); // 2 ingressos → 1 e-mail
        $this->issue($session, $bia);
        $refunded = $this->issue($session, $estornado);
        Ticket::where('order_id', $refunded->id)->update(['status' => Ticket::STATUS_REFUNDED]);

        $this->artisan('kena:send-day-of-reminders')->assertExitCode(0);
        $this->artisan('kena:send-day-of-reminders')->assertExitCode(0); // 2ª vez não reenvia

        Mail::assertQueued(EventDayReminderMail::class, 2);
        Mail::assertQueued(EventDayReminderMail::class, fn ($m) => $m->hasTo('ana@example.com'));
        Mail::assertQueued(EventDayReminderMail::class, fn ($m) => $m->hasTo('bia@example.com'));
        Mail::assertNotQueued(EventDayReminderMail::class, fn ($m) => $m->hasTo('estorno@example.com'));
        $this->assertNotNull($session->refresh()->day_of_reminded_at);
    }

    public function test_does_not_send_on_other_days_or_after_start(): void
    {
        Mail::fake();
        $session = $this->makeSession(2);
        $session->update(['starts_at' => '2026-11-20 16:00:00']);
        $this->issue($session, User::factory()->create());

        Carbon::setTestNow('2026-11-19 08:00:00'); // véspera: é o lembrete D-1, não este
        $this->artisan('kena:send-day-of-reminders')->assertExitCode(0);
        Carbon::setTestNow('2026-11-20 17:00:00'); // já começou
        $this->artisan('kena:send-day-of-reminders')->assertExitCode(0);

        Mail::assertNotQueued(EventDayReminderMail::class);
        $this->assertNull($session->refresh()->day_of_reminded_at);
    }

    public function test_mail_has_button_to_my_tickets(): void
    {
        $session = $this->makeSession(1);
        $buyer = User::factory()->create(['password' => 'secret-pass']);
        $this->issue($session, $buyer);

        $mail = new EventDayReminderMail($session, $buyer);
        $mail->assertHasSubject('É hoje: '.$session->event->title);
        $mail->assertSeeInHtml('Ver meus ingressos');
        $mail->assertSeeInHtml(route('tickets.index'), false);
    }

    public function test_is_scheduled_daily_in_the_morning(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'kena:send-day-of-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
    }
}
