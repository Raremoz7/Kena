<?php

namespace Tests\Feature\Kena;

use App\Exceptions\TransferException;
use App\Models\CheckIn;
use App\Models\EventSession;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Services\TicketTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Corridas de check-in e transferência: updates devem ser condicionais ao status. */
class CheckInRaceTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    /** @return array{0: Ticket, 1: EventSession, 2: User} */
    private function makePaidTicket(): array
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return [Ticket::where('order_id', $order->id)->firstOrFail(), $session, $user];
    }

    public function test_two_simultaneous_scans_admit_only_one(): void
    {
        [$ticket, $session] = $this->makePaidTicket();

        // Dois leitores carregam o MESMO ingresso antes de qualquer admissão.
        $scannerA = Ticket::findOrFail($ticket->id);
        $scannerB = Ticket::findOrFail($ticket->id);

        $first = app(CheckInService::class)->admit($scannerA, $session, null);
        $second = app(CheckInService::class)->admit($scannerB, $session, null); // modelo stale (VALID em memória)

        $this->assertSame(CheckIn::RESULT_OK, $first['result']);
        $this->assertSame(CheckIn::RESULT_DENIED, $second['result'], 'segundo scan simultâneo não pode admitir');
        $this->assertSame(1, Ticket::where('id', $ticket->id)->where('status', Ticket::STATUS_USED)->count());
    }

    public function test_transfer_with_stale_model_after_check_in_is_rejected(): void
    {
        [$ticket, $session, $owner] = $this->makePaidTicket();

        // Titular abre a tela de transferência (modelo carregado, VALID)...
        $staleForTransfer = Ticket::findOrFail($ticket->id);

        // ...mas entra na sessão antes de confirmar a transferência.
        app(CheckInService::class)->admit(Ticket::findOrFail($ticket->id), $session, null);

        $this->expectException(TransferException::class);
        app(TicketTransferService::class)->transfer($staleForTransfer, $owner, 'amigo@example.com');
    }

    public function test_transfer_race_does_not_leave_two_usable_tickets(): void
    {
        [$ticket, $session, $owner] = $this->makePaidTicket();

        $staleForTransfer = Ticket::findOrFail($ticket->id);
        app(CheckInService::class)->admit(Ticket::findOrFail($ticket->id), $session, null);

        try {
            app(TicketTransferService::class)->transfer($staleForTransfer, $owner, 'amigo@example.com');
        } catch (TransferException) {
            // esperado
        }

        // Não pode existir um segundo ingresso VALID emitido pela transferência.
        $this->assertSame(0, Ticket::where('status', Ticket::STATUS_VALID)->count());
        $this->assertSame(1, Ticket::where('status', Ticket::STATUS_USED)->count());
    }
}
