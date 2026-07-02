<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Libera holds de assentos vencidos a cada minuto.
Schedule::command('kena:expire-reservations')->everyMinute()->withoutOverlapping();

// Rede de segurança do pagamento: reconcilia pendentes com o MP e expira Pix vencido.
Schedule::command('kena:reconcile-payments')->everyMinute()->withoutOverlapping();

// Lembrete D-1 aos titulares (uma vez por dia).
Schedule::command('kena:send-reminders')->dailyAt('09:00')->withoutOverlapping();
