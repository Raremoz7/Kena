<?php

namespace App\Console\Commands;

use App\Services\SeatReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'kena:expire-reservations';

    protected $description = 'Libera holds de assentos vencidos e marca reservas expiradas';

    public function handle(SeatReservationService $reservations): int
    {
        $freed = $reservations->expireDueHolds();
        $this->info("Holds liberados: {$freed}");

        return self::SUCCESS;
    }
}
