<?php

namespace Tests\Feature\Kena;

use App\Mail\TicketsIssuedMail;
use App\Mail\TicketTransferredMail;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Venue;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Support\SampleTicketOrder;
use App\Support\TicketImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class TicketImageTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function ticket(string $title = 'O Quebra-Nozes', ?string $banner = '/img/quebra-nozes.jpg', string $holder = 'Davi Moreira'): Ticket
    {
        $venue = new Venue(['name' => 'Teatro UNIP', 'city' => 'Brasília', 'state' => 'DF']);
        $event = new Event(['title' => $title, 'banner_image' => $banner]);
        $event->setRelation('venue', $venue);
        $session = new EventSession([
            'starts_at' => Carbon::parse('2026-11-20 16:00'),
            'doors_at' => Carbon::parse('2026-11-20 15:30'),
        ]);
        $session->setRelation('event', $event);
        $ticket = new Ticket([
            'code' => 'KNA-000123',
            'sector_name' => 'Plateia',
            'seat_code' => 'A10',
            'holder_name' => $holder,
            'qr_token' => 'KNA-000123.abc1234567.deadbeefcafef00d',
        ]);
        $ticket->setRelation('session', $session);

        return $ticket;
    }

    /** @return array{int, int} */
    private function pngSize(string $png): array
    {
        $info = getimagesizefromstring($png);
        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);

        return [$info[0], $info[1]];
    }

    public function test_renders_a_vertical_png(): void
    {
        [$w, $h] = $this->pngSize(TicketImage::png($this->ticket()));

        $this->assertSame(640, $w);
        $this->assertGreaterThan($w * 1.8, $h, 'ingresso deve ser vertical');
    }

    public function test_long_title_and_name_grow_the_card_without_breaking(): void
    {
        [, $short] = $this->pngSize(TicketImage::png($this->ticket()));
        [$w, $long] = $this->pngSize(TicketImage::png($this->ticket(
            title: 'Espetáculo de fim de ano da Escola de Ballet com um título bem comprido',
            holder: 'Maria Aparecida dos Santos de Oliveira Albuquerque',
        )));

        $this->assertSame(640, $w);
        $this->assertGreaterThan($short, $long); // título em duas linhas
    }

    public function test_works_without_event_photo(): void
    {
        [$w] = $this->pngSize(TicketImage::png($this->ticket(banner: null)));
        [$w2] = $this->pngSize(TicketImage::png($this->ticket(banner: '/img/nao-existe.jpg')));

        $this->assertSame(640, $w);
        $this->assertSame(640, $w2);
    }

    public function test_tickets_email_embeds_the_ticket_image(): void
    {
        $html = (new TicketsIssuedMail(SampleTicketOrder::make('Davi Teste'), preview: true))->render();

        $this->assertStringContainsString('alt="Ingresso TESTE-0001', $html);
        $this->assertStringContainsString('alt="Ingresso TESTE-0002', $html);
        $this->assertStringContainsString('Lugar A10', $html);
    }

    public function test_transfer_email_embeds_the_ticket_image(): void
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);
        $ticket = $order->tickets()->firstOrFail();

        $html = (new TicketTransferredMail($ticket, 'Davi'))->render();

        $this->assertStringContainsString('alt="Ingresso '.$ticket->code, $html);
    }
}
