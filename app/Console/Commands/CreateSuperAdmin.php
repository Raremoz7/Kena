<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cria (ou troca a senha de) uma conta de super admin da plataforma (/admin).
 * Conta separada das contas do painel dos organizadores.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'kena:super-admin {email} {--name=} {--remove : Apaga a conta}';

    protected $description = 'Cria ou atualiza uma conta de super admin da plataforma (/admin)';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if ($this->option('remove')) {
            $deleted = PlatformAdmin::where('email', $email)->delete();
            $this->info($deleted > 0 ? "Super admin {$email} removido." : 'Conta não encontrada.');

            return self::SUCCESS;
        }

        $password = (string) $this->secret('Senha (mínimo 8, com letra e número)');
        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        PlatformAdmin::updateOrCreate(
            ['email' => $email],
            ['name' => (string) ($this->option('name') ?: 'Super admin'), 'password' => $password],
        );
        $this->info("Super admin {$email} pronto. Entre em /admin/login.");

        return self::SUCCESS;
    }
}
