<?php

namespace Tests\Feature\Kena;

use App\Http\Controllers\Admin\CourtesyController;
use App\Models\Coupon;
use App\Models\PanelUser;
use App\Models\User;
use App\Services\CourtesyService;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Cortesias nominais: geração em lote, prazo e relatório de retiradas. */
class CourtesyAdminTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new FakeGateway);
    }

    private function organizer(): PanelUser
    {
        return PanelUser::factory()->create();
    }

    public function test_list_parsing_accepts_semicolon_tab_and_name_only(): void
    {
        $people = CourtesyService::parseList("Ana Clara; Escola A\nBeatriz\tEscola B\n\n  Carla  \n");

        $this->assertSame([
            ['name' => 'Ana Clara', 'group' => 'Escola A'],
            ['name' => 'Beatriz', 'group' => 'Escola B'],
            ['name' => 'Carla', 'group' => null],
        ], $people);
    }

    public function test_prefix_comes_from_the_event_title(): void
    {
        $this->assertSame('QN', CourtesyController::prefixFor('O Quebra-Nozes'));
        $this->assertSame('LDC', CourtesyController::prefixFor('O Lago dos Cisnes'));
    }

    public function test_organizer_generates_one_code_per_person(): void
    {
        $session = $this->makeSession(4);

        $this->actingAs($this->organizer(), 'painel')
            ->post(route('admin.courtesies.store'), [
                'event_id' => $session->event_id,
                'people' => "Ana; Escola A\nBia; Escola B",
                'tickets' => 2,
                'expires_at' => '2030-11-10',
                'prefix' => 'qn',
            ])
            ->assertRedirect(route('admin.courtesies', ['event' => $session->event_id]));

        $coupons = Coupon::orderBy('beneficiary')->get();
        $this->assertCount(2, $coupons);
        $this->assertSame(['Ana', 'Bia'], $coupons->pluck('beneficiary')->all());
        $this->assertSame(['Escola A', 'Escola B'], $coupons->pluck('group_name')->all());
        foreach ($coupons as $c) {
            $this->assertMatchesRegularExpression('/^QN-[A-Z2-9]{6}$/', $c->code);
            $this->assertSame(Coupon::TYPE_TICKETS, $c->type);
            $this->assertSame(2, $c->value);
            $this->assertSame(1, $c->max_uses);
            $this->assertSame($session->event_id, $c->event_id);
            // Prazo vale até o fim do dia escolhido.
            $this->assertSame('2030-11-10 23:59:59', $c->expires_at?->format('Y-m-d H:i:s'));
        }
    }

    public function test_empty_list_is_rejected(): void
    {
        $session = $this->makeSession(1);

        $this->actingAs($this->organizer(), 'painel')
            ->post(route('admin.courtesies.store'), ['event_id' => $session->event_id, 'people' => "  \n ", 'tickets' => 2])
            ->assertSessionHasErrors('people');
    }

    public function test_report_shows_who_redeemed_and_who_did_not(): void
    {
        $session = $this->makeSession(4);
        $event = $session->event;
        $courtesies = app(CourtesyService::class);
        [$ana, $bia] = $courtesies->generate($event, [['name' => 'Ana', 'group' => 'A'], ['name' => 'Bia', 'group' => 'B']], 2, null, 'QN')->all();
        $expired = $courtesies->generate($event, [['name' => 'Carla', 'group' => 'A']], 2, now()->subDay(), 'QN')->first();

        $parent = User::factory()->create(['name' => 'Mãe da Ana', 'phone' => '61999990000']);
        $reservation = app(SeatReservationService::class)->hold($session, $parent, $this->availableSeatIds($session, 2));
        app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => $ana->code]);

        $rows = collect($courtesies->report($event))->keyBy('beneficiary');

        $this->assertSame(CourtesyService::STATUS_REDEEMED, $rows['Ana']['status']);
        $this->assertSame('Mãe da Ana', $rows['Ana']['redeemedBy']['name']);
        $this->assertSame('61999990000', $rows['Ana']['redeemedBy']['phone']);
        $this->assertCount(2, $rows['Ana']['seats']);
        $this->assertSame(CourtesyService::STATUS_PENDING, $rows['Bia']['status']);
        $this->assertNull($rows['Bia']['redeemedBy']);
        $this->assertSame(CourtesyService::STATUS_EXPIRED, $rows['Carla']['status']);
        $this->assertSame($expired->code, $rows['Carla']['code']);
        $this->assertNotSame($bia->code, $ana->code);
    }

    public function test_expired_courtesy_cannot_be_used(): void
    {
        $session = $this->makeSession(2);
        $coupon = app(CourtesyService::class)->generate($session->event, [['name' => 'Ana', 'group' => null]], 2, now()->subMinute(), 'QN')->first();

        $this->assertFalse($coupon->isRedeemable($session->event_id));
    }

    public function test_panel_page_and_csv_export(): void
    {
        $session = $this->makeSession(2);
        app(CourtesyService::class)->generate($session->event, [['name' => 'Ana Ç', 'group' => 'Escola A']], 2, null, 'QN');
        $organizer = $this->organizer();

        $this->actingAs($organizer, 'painel')->get(route('admin.courtesies', ['event' => $session->event_id]))->assertOk();

        $csv = $this->actingAs($organizer, 'painel')
            ->get(route('admin.courtesies.export', ['event' => $session->event_id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Nome;Grupo;Código', $csv);
        $this->assertStringContainsString('"Ana Ç";"Escola A"', $csv);
        $this->assertStringContainsString('Não retirada', $csv);
    }

    public function test_staff_cannot_see_courtesies(): void
    {
        $staff = PanelUser::factory()->create(['role' => PanelUser::ROLE_STAFF]);

        $this->actingAs($staff, 'painel')->get(route('admin.courtesies'))->assertForbidden();
    }
}
