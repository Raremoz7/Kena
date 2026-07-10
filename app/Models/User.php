<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property string|null $phone
 * @property string|null $cpf
 * @property string|null $google_id
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'role', 'phone', 'cpf', 'google_id', 'password', 'is_admin'])]
#[Hidden(['password', 'cpf', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'magic_login_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const ROLE_BUYER = 'buyer';

    public const ROLE_ORGANIZER = 'organizer';

    public const ROLE_STAFF = 'staff';

    /** Organizador ou staff acessam o painel/check-in. */
    public function canManageEvents(): bool
    {
        return in_array($this->role, [self::ROLE_ORGANIZER, self::ROLE_STAFF], true);
    }

    /**
     * Gestão sensível (eventos, cupons, locais, pedidos, config, equipe):
     * só organizador ou admin. Staff fica restrito ao check-in.
     */
    public function canManageOrganization(): bool
    {
        return $this->role === self::ROLE_ORGANIZER || $this->is_admin;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
