<?php

namespace App\Models;

use Database\Factories\PanelUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Conta do painel — organizador ou staff de check-in. Vive fora de `users`,
 * que e so do comprador: guard proprio, sessao propria, sem ponte entre os dois.
 *
 * @property string $name
 * @property string $email
 * @property string $role
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class PanelUser extends Authenticatable
{
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
        ];
    }
}
