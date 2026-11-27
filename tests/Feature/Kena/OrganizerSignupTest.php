<?php

namespace Tests\Feature\Kena;

use App\Mail\OrganizerVerifyEmailMail;
use App\Models\Organization;
use App\Models\PanelUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Cadastro aberto de organizador. */
class OrganizerSignupTest extends TestCase
{
    use RefreshDatabase;

    private function enableSignup(): void
    {
        config(['kena.organizer_signup.enabled' => true, 'kena.mercadopago.oauth.enabled' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'organization_name' => 'Cia Teatro Aberto',
            'name' => 'Ana Produtora',
            'email' => 'ana@gmail.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'accept_terms' => '1',
        ];
    }

    public function test_signup_does_not_exist_while_disabled(): void
    {
        $this->get('/organizador/cadastro')->assertNotFound();
        $this->post('/organizador/cadastro', $this->payload())->assertNotFound();

        // O cadastro sozinho não basta: sem OAuth a venda cairia na conta da plataforma.
        config(['kena.organizer_signup.enabled' => true]);
        $this->get('/organizador/cadastro')->assertNotFound();
    }

    public function test_signup_creates_organization_and_requires_email_confirmation(): void
    {
        $this->enableSignup();
        Mail::fake();

        $this->post('/organizador/cadastro', $this->payload())->assertRedirect('/painel/login');

        $org = Organization::where('name', 'Cia Teatro Aberto')->firstOrFail();
        $user = PanelUser::where('email', 'ana@gmail.com')->firstOrFail();
        $this->assertSame('cia-teatro-aberto', $org->slug);
        $this->assertNotNull($org->terms_accepted_at);
        $this->assertSame($org->id, $user->organization_id);
        $this->assertSame(PanelUser::ROLE_ORGANIZER, $user->role);
        $this->assertNull($user->email_verified_at);

        $sent = null;
        Mail::assertSent(OrganizerVerifyEmailMail::class, function (OrganizerVerifyEmailMail $mail) use (&$sent): bool {
            $sent = $mail->url;

            return $mail->hasTo('ana@gmail.com');
        });

        // Antes de confirmar, não entra (e o link é reenviado).
        $this->post('/painel/login', ['email' => 'ana@gmail.com', 'password' => 'senha1234'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('painel');

        // O link confirma, entra e leva para conectar o Mercado Pago.
        $this->get((string) $sent)->assertRedirect('/painel/config');
        $this->assertAuthenticatedAs($user, 'painel');
        $this->assertNotNull($user->fresh()?->email_verified_at);

        // Uso único: depois de confirmado, o link não entra mais sozinho.
        $this->post('/painel/logout');
        $this->get((string) $sent)->assertRedirect('/painel/login');
        $this->assertGuest('painel');
    }

    public function test_signup_requires_terms_and_unique_email(): void
    {
        $this->enableSignup();
        Mail::fake();
        PanelUser::factory()->create(['email' => 'ana@gmail.com']);

        $this->post('/organizador/cadastro', $this->payload(['accept_terms' => null]))
            ->assertSessionHasErrors(['accept_terms', 'email']);
        $this->assertSame(1, Organization::count());
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = PanelUser::factory()->create(['email_verified_at' => null]);

        $this->get("/organizador/verificar/{$user->id}")->assertForbidden();
        $this->assertGuest('painel');
    }

    public function test_team_members_created_by_organizer_can_log_in(): void
    {
        $this->actingAs(PanelUser::factory()->create(), 'painel')->post('/painel/equipe', [
            'name' => 'Porteiro', 'email' => 'porteiro@teatro.test', 'role' => 'staff', 'password' => 'senha1234',
        ]);
        $this->post('/painel/logout');

        $this->post('/painel/login', ['email' => 'porteiro@teatro.test', 'password' => 'senha1234'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated('painel');
    }
}
