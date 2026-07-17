<?php

namespace Database\Factories;

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
            'remember_token' => Str::random(10),
        ];
    }

    /** Staff só alcança o check-in. */
    public function staff(): static
    {
        return $this->state(fn () => ['role' => PanelUser::ROLE_STAFF]);
    }
}
