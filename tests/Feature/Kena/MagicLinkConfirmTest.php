<?php

namespace Tests\Feature\Kena;

use App\Mail\TicketsIssuedMail;
use App\Models\User;
use App\Support\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Link de acesso do e-mail: só para conta sem senha e sem Google, e entrar
 * exige tocar no botão (abrir o link não gasta o token).
 */
class MagicLinkConfirmTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_scanner_opening_the_link_does_not_spend_it(): void
    {
        $user = User::factory()->create(['name' => 'Ana Souza', 'password' => null]);
        $url = MagicLink::generate($user, now()->addDays(10));

        // O antivírus abre o link duas vezes: só vê a página, ninguém entra.
        $this->get($url)->assertOk();
        $confirmUrl = null;
        $this->get($url)
            ->assertInertia(function (Assert $p) use (&$confirmUrl) {
                $p->component('auth/magic-login')->where('firstName', 'Ana');
                $confirmUrl = $p->toArray()['props']['confirmUrl'];
            });
        $this->assertGuest();

        // A pessoa toca em Entrar (o formulário posta na URL que a página recebeu).
        $this->post($confirmUrl)->assertRedirect(route('tickets.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_with_password_cannot_enter_by_email_link(): void
    {
        $user = User::factory()->create(['password' => 'senha-certa-1']);
        // Link antigo, enviado antes da regra.
        $url = MagicLink::generate($user, now()->addDays(10));

        $this->get($url)->assertRedirect(route('login'));
        $this->post($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_google_account_cannot_enter_by_email_link(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-1']);
        $url = MagicLink::generate($user, now()->addDays(10));

        $this->post($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tickets_email_only_carries_the_access_link_for_accounts_without_password(): void
    {
        $guest = User::factory()->create(['password' => null]);
        $withPassword = User::factory()->create(['password' => 'senha-certa-1']);
        $google = User::factory()->create(['password' => null, 'google_id' => 'g-2']);

        $this->assertStringContainsString('/entrar/', TicketsIssuedMail::magicLink($guest->id, now()->addDays(5)));
        $this->assertSame(route('tickets.index'), TicketsIssuedMail::magicLink($withPassword->id, now()->addDays(5)));
        $this->assertSame(route('tickets.index'), TicketsIssuedMail::magicLink($google->id, now()->addDays(5)));
        $this->assertNull($withPassword->fresh()->magic_login_token);
    }

    public function test_already_logged_in_owner_goes_straight_to_tickets(): void
    {
        $user = User::factory()->create(['password' => null]);
        $url = MagicLink::generate($user, now()->addDays(10));

        $this->actingAs($user)->get($url)->assertRedirect(route('tickets.index'));
    }
}
