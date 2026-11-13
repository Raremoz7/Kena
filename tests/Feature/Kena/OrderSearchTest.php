<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGateway;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Busca do painel de pedidos: nome, e-mail, referência, assento e código do ingresso. */
class OrderSearchTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    /** @var array{maria: Order, joao: Order} */
    private array $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        Mail::fake();

        // A1 → Maria, A2 → João (mesma sessão).
        $session = $this->makeSession(2, 4500);
        $seatIds = $this->availableSeatIds($session, 2);

        $this->orders = [
            'maria' => $this->buy($session, User::factory()->create(['name' => 'Maria Souza', 'email' => 'maria@example.com']), $seatIds[0]),
            'joao' => $this->buy($session, User::factory()->create(['name' => 'João Lima', 'email' => 'joao@example.com']), $seatIds[1]),
        ];
    }

    private function buy($session, User $user, int $seatId): Order
    {
        $reservation = app(SeatReservationService::class)->hold($session, $user, [$seatId]);

        return app(PaymentService::class)
            ->pay($reservation, ['method' => 'card', 'token' => 'tok'])
            ->refresh();
    }

    /** @return list<int> ids dos pedidos retornados pela busca */
    private function search(?string $term): array
    {
        $ids = [];
        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->get(route('admin.orders', $term === null ? [] : ['q' => $term]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids): void {
                $ids = collect($page->toArray()['props']['orders']['data'])->pluck('id')->all();
            });

        return $ids;
    }

    public function test_without_a_term_every_order_is_listed(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->orders['maria']->id, $this->orders['joao']->id],
            $this->search(null),
        );
    }

    public function test_finds_by_buyer_name_case_insensitive_and_partial(): void
    {
        $this->assertSame([$this->orders['maria']->id], $this->search('mari'));
        $this->assertSame([$this->orders['joao']->id], $this->search('LIMA'));
    }

    public function test_finds_by_email(): void
    {
        $this->assertSame([$this->orders['joao']->id], $this->search('joao@example'));
    }

    public function test_finds_by_order_reference(): void
    {
        $reference = $this->orders['maria']->reference;

        $this->assertSame([$this->orders['maria']->id], $this->search($reference));
    }

    /**
     * Seat A1 é seguro para buscar: os códigos aleatórios (ingresso e pedido)
     * nunca contêm o dígito 1, então nada além do assento casa com "A1".
     *
     * @return array{0: Order, 1: Order} dono do assento A1 e o outro pedido
     */
    private function ownerOfSeatA1(): array
    {
        $ownerId = Ticket::where('seat_code', 'A1')->sole()->order_id;
        $owner = collect($this->orders)->firstWhere('id', $ownerId);
        $other = collect($this->orders)->firstWhere('id', '!=', $ownerId);

        return [$owner, $other];
    }

    public function test_finds_by_seat(): void
    {
        [$owner] = $this->ownerOfSeatA1();

        $this->assertSame([$owner->id], $this->search('A1'));
    }

    public function test_finds_by_ticket_code(): void
    {
        $ticket = Ticket::where('order_id', $this->orders['joao']->id)->sole();

        $this->assertContains($this->orders['joao']->id, $this->search($ticket->code));
    }

    public function test_every_word_must_match_somewhere(): void
    {
        [$owner, $other] = $this->ownerOfSeatA1();
        $ownerName = explode(' ', $owner->user->name)[0];
        $otherName = explode(' ', $other->user->name)[0];

        $this->assertSame([$owner->id], $this->search("{$ownerName} A1"));
        $this->assertSame([], $this->search("{$otherName} A1"), 'o outro comprador não está no assento A1');
    }

    public function test_no_match_returns_empty_and_echoes_the_term(): void
    {
        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->get(route('admin.orders', ['q' => 'ninguem-aqui']))
            ->assertInertia(fn (Assert $p) => $p
                ->has('orders.data', 0)
                ->where('search', 'ninguem-aqui'));
    }

    public function test_search_is_trimmed_and_blank_means_no_filter(): void
    {
        $this->assertCount(2, $this->search('   '));
        $this->assertSame([$this->orders['maria']->id], $this->search('  maria  '));
    }
}
