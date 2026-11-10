<?php

namespace App\Console\Commands;

use App\Mail\EventDayReminderMail;
use App\Models\EventSession;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envia o lembrete "É hoje" para os titulares de ingresso válido das sessões
 * que acontecem hoje e ainda não começaram. Cada sessão recebe uma vez só.
 */
class SendEventDayReminders extends Command
{
    protected $signature = 'kena:send-day-of-reminders';

    protected $description = 'Envia lembrete no dia do evento aos titulares de ingresso';

    public function handle(): int
    {
        $sessions = EventSession::query()
            ->where('status', '!=', 'cancelled')
            ->whereNull('day_of_reminded_at')
            ->whereBetween('starts_at', [now(), now()->endOfDay()])
            ->get();

        $sent = 0;
        foreach ($sessions as $session) {
            // Marca antes de enfileirar: se algo falhar no meio, a próxima
            // execução não reenvia para quem já recebeu.
            $session->update(['day_of_reminded_at' => now()]);

            $userIds = Ticket::query()
                ->where('session_id', $session->id)
                ->where('status', Ticket::STATUS_VALID)
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $user = User::find((int) $userId);
                if ($user !== null && filled($user->email)) {
                    Mail::to($user->email)->queue(new EventDayReminderMail($session, $user));
                    $sent++;
                }
            }
        }

        $this->info("Lembretes do dia enviados: {$sent} (sessões: {$sessions->count()})");

        return self::SUCCESS;
    }
}
