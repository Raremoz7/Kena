<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->unverified()->create();
        $code = app(EmailVerificationCodeService::class)->issue('test@example.com');

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test@Example.com',
                'code' => $code,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        // O código confirmou o novo endereço.
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_change_requires_code_sent_to_new_address()
    {
        $user = User::factory()->create(['email' => 'eu@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Eu', 'email' => 'vitima@example.com'])
            ->assertSessionHasErrors('code');

        // Código de outro endereço (o próprio) não serve para o novo.
        $ownCode = app(EmailVerificationCodeService::class)->issue('eu@example.com');
        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Eu', 'email' => 'vitima@example.com', 'code' => $ownCode])
            ->assertSessionHasErrors('code');

        $this->assertSame('eu@example.com', $user->fresh()->email);
    }

    public function test_name_can_change_without_code()
    {
        $user = User::factory()->create(['email' => 'eu@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Novo Nome', 'email' => 'EU@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Novo Nome', $user->fresh()->name);
        $this->assertSame('eu@example.com', $user->fresh()->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
