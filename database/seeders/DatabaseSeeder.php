<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed da aplicação.
     *
     * - Usuários demo (comprador/organizador) só são criados FORA de produção.
     * - O admin é criado a partir de ADMIN_EMAIL / ADMIN_PASSWORD no .env
     *   (nenhuma credencial fica hardcoded no repositório). Em produção,
     *   defina essas variáveis, rode o seed uma vez e remova a senha do .env.
     */
    public function run(): void
    {
        $this->seedAdminFromEnv();

        if (! app()->isProduction()) {
            $this->seedDemoUsers();
        }

        $this->call(KenaSeeder::class);
    }

    /** Cria/atualiza o admin a partir das variáveis de ambiente, se definidas. */
    private function seedAdminFromEnv(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => $password,
                'is_admin' => true,
                'role' => User::ROLE_ORGANIZER,
                'email_verified_at' => now(),
            ],
        );
    }

    /** Usuários de demonstração para as telas autenticadas (nunca em produção). */
    private function seedDemoUsers(): void
    {
        User::updateOrCreate(
            ['email' => 'helena@veludo.test'],
            [
                'name' => 'Helena Drummond',
                'password' => 'veludo123',
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'organizador@veludo.test'],
            [
                'name' => 'Produção Kena',
                'password' => 'veludo123',
                'role' => User::ROLE_ORGANIZER,
                'email_verified_at' => now(),
            ],
        );
    }
}
