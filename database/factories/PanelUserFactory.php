<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PanelUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<PanelUser>
 */
class PanelUserFactory extends Factory
{
    protected $model = PanelUser::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => PanelUser::ROLE_ORGANIZER,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /** Staff só alcança o check-in. */
    public function staff(): static
    {
        return $this->state(fn () => ['role' => PanelUser::ROLE_STAFF]);
    }

    /** Admin da plataforma (Somo). */
    public function platformAdmin(): static
    {
        return $this->state(fn () => ['is_platform_admin' => true]);
    }

    /** Organizador de outra organização (cria a organização junto). */
    public function forOrganization(?Organization $organization = null): static
    {
        return $this->state(fn () => [
            'organization_id' => ($organization ?? Organization::create([
                'name' => $name = fake()->company(),
                'slug' => Organization::uniqueSlug($name),
            ]))->id,
        ]);
    }
}
