<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed da aplicação. Cria o comprador demo (Veludo) para as telas
     * autenticadas (checkout, meus ingressos).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'helena@veludo.test'],
            [
                'name' => 'Helena Drummond',
                'password' => 'veludo123',
                'email_verified_at' => now(),
            ],
        );

        // Admin (Davi)
        User::updateOrCreate(
            ['email' => 'davimoreira10@gmail.com'],
            [
                'name' => 'Davi Moreira',
                'password' => '35227066Da',
                'is_admin' => true,
                'role' => User::ROLE_ORGANIZER,
                'email_verified_at' => now(),
            ],
        );

        // Organizador demo (painel + check-in)
        User::updateOrCreate(
            ['email' => 'organizador@veludo.test'],
            [
                'name' => 'Produção Kena',
                'password' => 'veludo123',
                'role' => User::ROLE_ORGANIZER,
                'email_verified_at' => now(),
            ],
        );

        $this->call(KenaSeeder::class);
    }
}
