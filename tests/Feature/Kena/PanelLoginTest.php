<?php

namespace Tests\Feature\Kena;

use App\Models\PanelUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * O login do painel e do comprador são portas independentes: guards, sessões e
 * tabelas distintas. Estes testes existem para essa separação não regredir.
 */
class PanelLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_login_screen_renders(): void
    {
        $this->get(route('painel.login'))->assertOk();
    }

    public function test_organizer_signs_in_and_reaches_the_panel(): void
    {
        PanelUser::factory()->create([
            'email' => 'op@kena.test',
            'password' => Hash::make('senha-do-painel'),
        ]);

        $this->post(route('painel.login.store'), [
            'email' => 'op@kena.test',
            'password' => 'senha-do-painel',
        ])->assertRedirect(route('painel'));

        $this->assertAuthenticated('painel');
        // Entrar no painel não cria sessão de comprador.
        $this->assertGuest('web');
    }

    public function test_wrong_password_is_rejected(): void
    {
        PanelUser::factory()->create([
            'email' => 'op@kena.test',
            'password' => Hash::make('senha-do-painel'),
        ]);

        $this->post(route('painel.login.store'), [
            'email' => 'op@kena.test',
            'password' => 'chute',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('painel');
    }

    /** Credencial de comprador não abre o painel, mesmo estando correta. */
    public function test_buyer_credentials_do_not_open_the_panel(): void
    {
        User::factory()->create([
            'email' => 'helena@kena.test',
            'password' => Hash::make('senha-da-helena'),
        ]);

        $this->post(route('painel.login.store'), [
            'email' => 'helena@kena.test',
            'password' => 'senha-da-helena',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('painel');
    }

    /** Sair do painel não derruba a sessão do comprador. */
    public function test_panel_logout_leaves_buyer_session_alone(): void
    {
        $buyer = User::factory()->create();
        $organizer = PanelUser::factory()->create();

        $this->actingAs($buyer)->actingAs($organizer, 'painel');

        $this->post(route('painel.logout'))->assertRedirect(route('painel.login'));

        $this->assertGuest('painel');
        $this->assertAuthenticated('web');
    }
}
