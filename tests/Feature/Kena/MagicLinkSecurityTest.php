<?php

namespace Tests\Feature\Kena;

use App\Models\User;
use App\Support\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Magic-link: uso único, rotação e nada de bearer eterno. */
class MagicLinkSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_logs_in_once_and_only_once(): void
    {
        $user = User::factory()->create();
        $url = MagicLink::generate($user, now()->addDays(10));

        // 1º clique: loga.
        $this->get($url)->assertRedirect(route('tickets.index'));
        $this->assertAuthenticatedAs($user);

        // E-mail encaminhado: 2º clique NÃO loga.
        auth()->logout();
        $this->flushSession();

        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_new_link_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create();
        $old = MagicLink::generate($user, now()->addDays(10));
        MagicLink::generate($user, now()->addDays(10)); // novo e-mail → novo link

        $this->get($old)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signed_url_without_token_does_not_authenticate(): void
    {
        $user = User::factory()->create();

        // URL assinada "crua" (como era antes): não pode mais logar sozinha.
        $bare = URL::temporarySignedRoute('magic-login', now()->addDay(), ['user' => $user->id]);

        $this->get($bare)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_link_expiry_is_capped_and_not_a_30_day_minimum(): void
    {
        $user = User::factory()->create();

        // Sessão daqui a 3 dias → link deve expirar perto de sessão+1d, não em 30d.
        MagicLink::generate($user, now()->addDays(3));
        $expiry = MagicLink::expiryFor(now()->addDays(3));

        $this->assertTrue($expiry->lessThanOrEqualTo(now()->addDays(4)->addMinute()));
    }
}
