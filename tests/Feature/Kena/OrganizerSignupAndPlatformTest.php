<?php

namespace Tests\Feature\Kena;

use App\Mail\OrganizerVerifyEmailMail;
use App\Models\Organization;
use App\Models\PanelUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Cadastro aberto de organizador e painel da plataforma. */
class OrganizerSignupAndPlatformTest extends TestCase
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
        $this->assertFalse($user->isPlatformAdmin());
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

    public function test_platform_page_is_only_for_platform_admin(): void
    {
        $this->actingAs(PanelUser::factory()->create(), 'painel')->get('/painel/plataforma')->assertForbidden();
        $b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);

        $this->actingAs(PanelUser::factory()->platformAdmin()->create(), 'painel')->get('/painel/plataforma')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/platform')
                ->has('organizations', 2)
                ->where('organizations.1.name', 'Teatro B'));

        $this->assertSame('teatro-b', $b->slug);
    }

    public function test_platform_admin_suspends_reactivates_and_sets_fee(): void
    {
        config(['kena.service_fee_percent' => 6]);
        $admin = PanelUser::factory()->platformAdmin()->create();
        $b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        $as = $this->actingAs($admin, 'painel');

        $as->post("/painel/plataforma/organizacoes/{$b->id}/suspender", [])->assertSessionHasErrors('reason');
        $as->post("/painel/plataforma/organizacoes/{$b->id}/suspender", ['reason' => 'Denúncia de evento falso']);
        $this->assertTrue($b->fresh()?->isSuspended());
        $this->assertSame('Denúncia de evento falso', $b->fresh()?->suspended_reason);

        $as->post("/painel/plataforma/organizacoes/{$b->id}/reativar");
        $this->assertFalse($b->fresh()?->isSuspended());

        $as->put("/painel/plataforma/organizacoes/{$b->id}/taxa", ['fee_percent' => 4.5]);
        $this->assertSame(4.5, $b->fresh()?->serviceFeePercent());
        $as->put("/painel/plataforma/organizacoes/{$b->id}/taxa", ['fee_percent' => 50])->assertSessionHasErrors('fee_percent');
        $as->put("/painel/plataforma/organizacoes/{$b->id}/taxa", ['fee_percent' => null]);
        $this->assertSame(6.0, $b->fresh()?->serviceFeePercent());
    }

    public function test_organizer_cannot_act_on_organizations(): void
    {
        $b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->post("/painel/plataforma/organizacoes/{$b->id}/suspender", ['reason' => 'x'])
            ->assertForbidden();
        $this->assertFalse($b->fresh()?->isSuspended());
    }

    public function test_platform_admin_command_grants_and_revokes(): void
    {
        $user = PanelUser::factory()->create(['email' => 'somo@somo.test']);

        $this->artisan('kena:platform-admin', ['email' => 'somo@somo.test'])->assertSuccessful();
        $this->assertTrue($user->fresh()?->isPlatformAdmin());

        $this->artisan('kena:platform-admin', ['email' => 'somo@somo.test', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()?->isPlatformAdmin());
    }
}
