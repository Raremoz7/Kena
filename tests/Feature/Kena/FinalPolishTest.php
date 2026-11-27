<?php

namespace Tests\Feature\Kena;

use App\Mail\PanelPasswordResetMail;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\PhotoGallery;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\Photos\PhotoOrderService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Taxa interna, primeiros passos, convite de organizador e registro. */
class FinalPolishTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_ticket_checkout_shows_no_fee_to_the_buyer(): void
    {
        config(['kena.service_fee_percent' => 6]);
        $session = $this->makeSession(2, 5000);
        $buyer = User::factory()->create(['cpf' => '52998224725']);
        $reservation = app(SeatReservationService::class)->hold($session, $buyer, $this->availableSeatIds($session, 2));

        $this->actingAs($buyer)->get(route('checkout', $reservation))
            ->assertInertia(fn (Assert $p) => $p
                ->where('priceSummary.total', fn ($v): bool => (float) $v === 100.0)
                ->where('priceSummary.lines', fn ($lines): bool => collect($lines)->every(fn ($l): bool => ! str_contains((string) $l['label'], 'Taxa'))));
    }

    public function test_photo_checkout_shows_no_fee_and_does_not_stack(): void
    {
        config(['kena.photo_fee_percent' => 6]);
        $session = $this->makeSession(1);
        $gallery = PhotoGallery::create([
            'event_id' => $session->event_id, 'status' => 'published', 'published_at' => now(),
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 2000]],
        ]);
        $photos = collect(range(1, 3))->map(fn (int $i) => $gallery->photos()->create([
            'filename' => "f{$i}.jpg", 'preview_path' => "fotos/{$gallery->id}/{$i}-preview.webp",
            'thumb_path' => "fotos/{$gallery->id}/{$i}-thumb.webp", 'width' => 400, 'height' => 300, 'position' => $i,
        ]));
        $buyer = User::factory()->create();

        $order = app(PhotoOrderService::class)->createFromSelection($buyer, $gallery, $photos->pluck('id')->all());

        $this->assertSame(6000, $order->total_cents); // só as fotos
        $this->assertSame(360, $order->fee_cents);    // taxa interna
        $this->actingAs($buyer)->get(route('photos.checkout', $order->reference))
            ->assertInertia(fn (Assert $p) => $p
                ->where('priceSummary.total', fn ($v): bool => (float) $v === 60.0)
                ->where('priceSummary.lines', fn ($lines): bool => collect($lines)->every(fn ($l): bool => ! str_contains((string) $l['label'], 'Taxa'))));
    }

    public function test_new_organizer_sees_first_steps_until_done(): void
    {
        $b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        $organizer = PanelUser::factory()->forOrganization($b)->create();

        $this->actingAs($organizer, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $p) => $p
                ->has('onboarding', 4)
                ->where('onboarding.0.key', 'profile')
                ->where('onboarding.0.done', false));

        // Organização de antes: tem evento, mas o nome provisório e a falta de
        // contato mantêm o passo de perfil aberto.
        $this->makeSession(1);
        $principal = PanelUser::factory()->create();
        $this->actingAs($principal, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $p) => $p->has('onboarding', 4)->where('onboarding.0.done', false)->has('kpis.platformFee'));

        // Perfil completo: o card some.
        Organization::findOrFail(Organization::DEFAULT_ID)->update(['name' => 'Teatro Real', 'contact_email' => 'oi@real.test']);
        $this->actingAs($principal, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $p) => $p->where('onboarding', null));
    }

    public function test_settings_show_the_public_page_link(): void
    {
        $this->actingAs(PanelUser::factory()->create(), 'painel')->get('/painel/config')
            ->assertInertia(fn (Assert $p) => $p->where('organizationUrl', route('organizers.show', 'principal')));
    }

    public function test_super_admin_creates_organization_and_invite_lasts_days(): void
    {
        Mail::fake();
        $admin = PlatformAdmin::factory()->create();

        $this->actingAs($admin, 'admin')->post('/admin/organizacoes', [
            'organization_name' => 'Cia Convidada', 'name' => 'Lia', 'email' => 'lia@cia.test',
        ])->assertSessionHasNoErrors();

        $org = Organization::where('name', 'Cia Convidada')->firstOrFail();
        $user = PanelUser::where('email', 'lia@cia.test')->firstOrFail();
        $this->assertSame($org->id, $user->organization_id);
        $this->assertSame('organization.create', PlatformAuditLog::latest('id')->value('action'));

        $url = null;
        Mail::assertSent(PanelPasswordResetMail::class, function (PanelPasswordResetMail $m) use (&$url): bool {
            $url = $m->url;

            return $m->invite && $m->hasTo('lia@cia.test');
        });
        preg_match('#/painel/redefinir-senha/([^?]+)#', (string) $url, $m);

        // Dois dias depois o convite ainda vale (o "esqueci minha senha" vale 60 min).
        $this->travel(2)->days();
        $this->post('/painel/redefinir-senha', [
            'token' => $m[1], 'email' => 'lia@cia.test', 'invite' => true,
            'password' => 'minhaSenha1', 'password_confirmation' => 'minhaSenha1',
        ])->assertRedirect('/painel/login');

        $this->post('/painel/login', ['email' => 'lia@cia.test', 'password' => 'minhaSenha1'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user, 'painel');
    }

    public function test_forgot_password_link_expires_in_an_hour(): void
    {
        Mail::fake();
        PanelUser::factory()->create(['email' => 'ana@teatro.test']);
        $this->post('/painel/esqueci-senha', ['email' => 'ana@teatro.test']);
        $url = null;
        Mail::assertSent(PanelPasswordResetMail::class, function (PanelPasswordResetMail $m) use (&$url): bool {
            $url = $m->url;

            return ! $m->invite;
        });
        preg_match('#/painel/redefinir-senha/([^?]+)#', (string) $url, $m);

        $this->travel(61)->minutes();
        $this->post('/painel/redefinir-senha', [
            'token' => $m[1], 'email' => 'ana@teatro.test',
            'password' => 'novaSenha123', 'password_confirmation' => 'novaSenha123',
        ])->assertSessionHasErrors('email');

        // Marcar como convite não estica o prazo: convite é outra tabela.
        $this->post('/painel/redefinir-senha', [
            'token' => $m[1], 'email' => 'ana@teatro.test', 'invite' => true,
            'password' => 'novaSenha123', 'password_confirmation' => 'novaSenha123',
        ])->assertSessionHasErrors('email');
    }

    public function test_audit_log_page_lists_actions(): void
    {
        $admin = PlatformAdmin::factory()->create(['name' => 'Somo']);
        $b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        $this->actingAs($admin, 'admin')->post("/admin/organizacoes/{$b->id}/suspender", ['reason' => 'Teste']);

        $this->actingAs($admin, 'admin')->get('/admin/registro')
            ->assertInertia(fn (Assert $p) => $p
                ->component('super/audit')
                ->where('logs.data.0.action', 'Suspendeu as vendas')
                ->where('logs.data.0.organization', 'Teatro B')
                ->where('logs.data.0.admin', 'Somo'));
    }
}
