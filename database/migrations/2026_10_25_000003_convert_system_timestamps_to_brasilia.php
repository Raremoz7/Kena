<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O app passou de UTC para o horário de Brasília (config/app.php). As datas que
 * o SISTEMA gravou (pedidos, reservas, pagamentos, logins…) estão em UTC e
 * voltam 3 horas para continuar no mesmo instante. As que foram DIGITADAS no
 * painel já estavam no relógio de Brasília e ficam como estão: horário e
 * abertura da sessão e prazo dos cupons/cortesias.
 */
return new class extends Migration
{
    private const OFFSET_HOURS = 3;

    /** Colunas digitadas no painel em horário de Brasília (não convertem). */
    private const WALL_CLOCK = [
        'event_sessions' => ['starts_at', 'doors_at'],
        'coupons' => ['expires_at'],
    ];

    public function up(): void
    {
        $this->shift(-self::OFFSET_HOURS);
    }

    public function down(): void
    {
        $this->shift(self::OFFSET_HOURS);
    }

    private function shift(int $hours): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            $columns = collect(Schema::getColumns($name))
                ->filter(fn (array $c): bool => in_array(strtolower($c['type_name']), ['datetime', 'timestamp'], true))
                ->pluck('name')
                ->reject(fn (string $c): bool => in_array($c, self::WALL_CLOCK[$name] ?? [], true));

            foreach ($columns as $column) {
                $expression = match ($driver) {
                    'sqlite' => DB::raw("datetime(\"{$column}\", '{$hours} hours')"),
                    default => DB::raw("DATE_ADD(`{$column}`, INTERVAL {$hours} HOUR)"),
                };

                DB::table($name)->whereNotNull($column)->update([$column => $expression]);
            }
        }
    }
};
