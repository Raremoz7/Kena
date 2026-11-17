<?php

namespace App\Console\Commands;

use App\Models\PanelUser;
use Illuminate\Console\Command;

/** Dá (ou tira, com --revoke) o papel de admin da plataforma a uma conta do painel. */
class GrantPlatformAdmin extends Command
{
    protected $signature = 'kena:platform-admin {email} {--revoke}';

    protected $description = 'Concede ou revoga o acesso de admin da plataforma a uma conta do painel';

    public function handle(): int
    {
        $user = PanelUser::withoutGlobalScopes()->where('email', (string) $this->argument('email'))->first();
        if ($user === null) {
            $this->error('Conta do painel não encontrada.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_platform_admin' => $grant])->save();
        $this->info($grant ? "{$user->email} agora é admin da plataforma." : "{$user->email} deixou de ser admin da plataforma.");

        return self::SUCCESS;
    }
}
