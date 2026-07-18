<?php

namespace Tests\Feature\Kena;

use App\Models\Coupon;
use App\Models\PanelUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_creates_percent_coupon(): void
    {
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')
            ->post(route('admin.coupons.store'), [
                'code' => 'promo20',
                'type' => 'percent',
                'value' => 20,
                'active' => true,
            ])
            ->assertRedirect(route('admin.coupons'));

        $coupon = Coupon::where('code', 'PROMO20')->firstOrFail();
        $this->assertSame('percent', $coupon->type);
        $this->assertSame(20, $coupon->value);
    }

    public function test_organizer_creates_fixed_coupon_in_reais(): void
    {
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')
            ->post(route('admin.coupons.store'), [
                'code' => 'menos15',
                'type' => 'fixed',
                'value' => 15,
                'active' => true,
            ])
            ->assertRedirect(route('admin.coupons'));

        // R$15 → 1500 centavos.
        $this->assertSame(1500, Coupon::where('code', 'MENOS15')->firstOrFail()->value);
    }

    public function test_buyer_cannot_access_coupons(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get(route('admin.coupons'))->assertRedirect(route('painel.login'));
    }
}
