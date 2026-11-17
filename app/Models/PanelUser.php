<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\PanelUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Conta do painel — organizador ou staff de check-in. Vive fora de `users`,
 * que e so do comprador: guard proprio, sessao propria, sem ponte entre os dois.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property Carbon|null $email_verified_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'organization_id', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class PanelUser extends Authenticatable
{
    use BelongsToOrganization;

    /** @use HasFactory<PanelUserFactory> */
    use HasFactory, Notifiable;

    /** Gestao completa do painel. */
    public const ROLE_ORGANIZER = 'organizer';

    /** So o check-in. */
    public const ROLE_STAFF = 'staff';

    /**
     * Gestao sensivel (eventos, cupons, locais, pedidos, config, equipe).
     * Staff fica restrito ao check-in.
     */
    public function canManageOrganization(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }
}
