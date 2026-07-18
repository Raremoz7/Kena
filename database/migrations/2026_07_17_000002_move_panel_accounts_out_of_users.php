<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move quem usa o painel (organizer/staff) de `users` para `panel_users`,
 * preservando o hash da senha — a mesma credencial passa a valer em
 * /painel/login. Depois disso `users` e so comprador, entao role e is_admin
 * saem: papel passa a existir num lugar so.
 */
return new class extends Migration
{
    public function up(): void
    {
        $panelAccounts = DB::table('users')
            ->whereIn('role', ['organizer', 'staff'])
            ->orWhere('is_admin', true)
            ->get();

        foreach ($panelAccounts as $account) {
            // Sem senha nao da para entrar no painel (so e-mail+senha la).
            if (blank($account->password)) {
                continue;
            }

            // is_admin virava gestao completa; staff continua staff.
            $role = ($account->role === 'staff' && ! $account->is_admin)
                ? 'staff'
                : 'organizer';

            DB::table('panel_users')->updateOrInsert(
                ['email' => $account->email],
                [
                    'name' => $account->name,
                    'password' => $account->password,
                    'role' => $role,
                    'created_at' => $account->created_at,
                    'updated_at' => now(),
                ],
            );

            DB::table('users')->where('id', $account->id)->delete();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_admin']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('buyer');
            $table->boolean('is_admin')->default(false);
        });

        foreach (DB::table('panel_users')->get() as $panelUser) {
            DB::table('users')->updateOrInsert(
                ['email' => $panelUser->email],
                [
                    'name' => $panelUser->name,
                    'password' => $panelUser->password,
                    'role' => $panelUser->role,
                    'is_admin' => $panelUser->role === 'organizer',
                    'email_verified_at' => now(),
                    'created_at' => $panelUser->created_at,
                    'updated_at' => now(),
                ],
            );
        }
    }
};
