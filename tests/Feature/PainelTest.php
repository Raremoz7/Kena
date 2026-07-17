<?php

namespace Tests\Feature;

use App\Models\PanelUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    /** Sem sessao de painel vai para o login DO PAINEL, nao o do comprador. */
    public function test_guests_are_redirected_to_the_panel_login()
    {
        $response = $this->get(route('painel'));
        $response->assertRedirect(route('painel.login'));
    }

    public function test_team_members_can_visit_the_painel()
    {
        $user = PanelUser::factory()->create();
        $this->actingAs($user, 'painel');

        $response = $this->get(route('painel'));
        $response->assertOk();
    }

    /** Conta de comprador nao existe no guard do painel: vai para /painel/login. */
    public function test_buyers_cannot_visit_the_painel()
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('painel'));
        $response->assertRedirect(route('painel.login'));
    }
}
