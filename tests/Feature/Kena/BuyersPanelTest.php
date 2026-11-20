<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\PanelUser;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Compradores no painel: lista com CPF mascarado, busca e desvincular CPF. */
class BuyersPanelTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function organizer(): PanelUser
    {
        return PanelUser::factory()->create();
    }

    private function buyerWithPaidOrder(): User
    {
        $session = $this->makeSession(2);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        app(OrderService::class)->createFromReservation($reservation)
            ->forceFill(['status' => Order::STATUS_PAID])->save();

        return $buyer;
    }

    public function test_staff_cannot_reach_buyers(): void
    {
        $staff = PanelUser::factory()->staff()->create();
        $buyer = User::factory()->create();

        $this->actingAs($staff, 'painel')->get(route('admin.buyers'))->assertRedirect(route('admin.checkin'));
        $this->actingAs($staff, 'painel')->delete(route('admin.buyers.unlink-cpf', $buyer))->assertForbidden();
        $this->assertNotNull($buyer->fresh()->cpf);
    }

    public function test_list_masks_cpf_showing_only_last_three_digits(): void
    {
        User::factory()->create(['cpf' => '52998224725', 'name' => 'Ana']);
        User::factory()->withoutCpf()->create(['name' => 'Bia']);

        $this->actingAs($this->organizer(), 'painel')
            ->get(route('admin.buyers'))
            ->assertOk()
            ->assertDontSee('52998224725')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/buyers')
                ->where('buyers.data', fn ($rows) => collect($rows)->firstWhere('name', 'Ana')['cpf'] === '•••.•••.••7-25'
                    && collect($rows)->firstWhere('name', 'Bia')['cpf'] === null));
    }

    public function test_search_by_full_cpf_finds_the_owner(): void
    {
        User::factory()->create(['cpf' => '52998224725', 'name' => 'Dona do CPF']);
        User::factory()->count(3)->create();

        $this->actingAs($this->organizer(), 'painel')
            ->get(route('admin.buyers', ['busca' => '529.982.247-25']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('buyers.data', 1)
                ->where('buyers.data.0.name', 'Dona do CPF'));
    }

    public function test_search_by_name_or_email(): void
    {
        User::factory()->create(['name' => 'Davi Teste', 'email' => 'x@a.test']);
        User::factory()->create(['name' => 'Outra', 'email' => 'davi@b.test']);
        User::factory()->create(['name' => 'Ninguem', 'email' => 'n@c.test']);

        $this->actingAs($this->organizer(), 'painel')
            ->get(route('admin.buyers', ['busca' => 'davi']))
            ->assertInertia(fn (Assert $page) => $page->has('buyers.data', 2));
    }

    public function test_list_counts_paid_orders(): void
    {
        $buyer = $this->buyerWithPaidOrder();

        $this->actingAs($this->organizer(), 'painel')
            ->get(route('admin.buyers'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('buyers.data', fn ($rows) => collect($rows)->firstWhere('id', $buyer->id)['paidOrders'] === 1));
    }

    public function test_organizer_unlinks_cpf_and_it_is_logged(): void
    {
        Log::spy();
        $organizer = $this->organizer();
        $buyer = User::factory()->create(['cpf' => '52998224725']);

        $this->actingAs($organizer, 'painel')
            ->delete(route('admin.buyers.unlink-cpf', $buyer))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull($buyer->fresh()->cpf);
        Log::shouldHaveReceived('info')->withArgs(fn ($msg, $ctx) => str_contains($msg, 'CPF desvinculado')
            && $ctx['buyer_id'] === $buyer->id
            && $ctx['panel_user_id'] === $organizer->id
            && ! str_contains(json_encode($ctx), '52998224725'))->once();
    }

    public function test_freed_cpf_can_be_used_by_another_account(): void
    {
        $old = User::factory()->create(['cpf' => '52998224725']);
        $this->actingAs($this->organizer(), 'painel')->delete(route('admin.buyers.unlink-cpf', $old));

        $new = User::factory()->withoutCpf()->create();
        $new->update(['cpf' => '52998224725']);

        $this->assertSame('52998224725', $new->fresh()->cpf);
    }
}
