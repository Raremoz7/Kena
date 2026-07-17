<?php

namespace Database\Seeders;

use App\Models\PanelUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed da aplicação.
     *
     * - Contas demo (comprador e painel) só são criadas FORA de produção.
     * - O organizador é criado a partir de ADMIN_EMAIL / ADMIN_PASSWORD no .env
     *   (nenhuma credencial fica hardcoded no repositório). Em produção,
     *   defina essas variáveis, rode o seed uma vez e remova a senha do .env.
     */
    public function run(): void
    {
        $this->seedOrganizerFromEnv();

        if (! app()->isProduction()) {
            $this->seedDemoAccounts();
        }

        $this->call(KenaSeeder::class);
    }

    /** Cria/atualiza o organizador do painel a partir do ambiente, se definido. */
    private function seedOrganizerFromEnv(): void
    {
        $email = config('kena.admin.email');
        $password = config('kena.admin.password');

        if (blank($email) || blank($password)) {
            return;
        }

        PanelUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('kena.admin.name', 'Administrador'),
                'password' => $password,
                'role' => PanelUser::ROLE_ORGANIZER,
            ],
        );
    }

    /** Contas de demonstração — nunca em produção. */
    private function seedDemoAccounts(): void
    {
        User::updateOrCreate(
            ['email' => 'helena@veludo.test'],
            [
                'name' => 'Helena Drummond',
                'password' => 'veludo123',
                'email_verified_at' => now(),
            ],
        );

        PanelUser::updateOrCreate(
            ['email' => 'organizador@veludo.test'],
            [
                'name' => 'Produção Kena',
                'password' => 'veludo123',
                'role' => PanelUser::ROLE_ORGANIZER,
            ],
        );

        PanelUser::updateOrCreate(
            ['email' => 'portaria@veludo.test'],
            [
                'name' => 'Portaria Kena',
                'password' => 'veludo123',
                'role' => PanelUser::ROLE_STAFF,
            ],
        );
    }
}
