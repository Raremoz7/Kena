<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\EventSession;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Envia o lembrete D-1 para os titulares de sessões que começam em ~24h. */
class SendEventReminders extends Command
{
    protected $signature = 'kena:send-reminders';

    protected $description = 'Envia lembrete (D-1) aos titulares de ingresso das próximas sessões';

    public function handle(): int
    {
        $sessions = EventSession::query()
            ->where('status', '!=', 'cancelled')
            ->whereNull('reminded_at')
            ->whereBetween('starts_at', [now(), now()->addHours(36)])
            ->get();

        $sent = 0;
        foreach ($sessions as $session) {
            $userIds = Ticket::query()
                ->where('session_id', $session->id)
                ->where('status', Ticket::STATUS_VALID)
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $user = User::find((int) $userId);
                if ($user !== null && filled($user->email)) {
                    Mail::to($user->email)->queue(new EventReminderMail($session, $user));
                    $sent++;
                }
            }

            $session->update(['reminded_at' => now()]);
        }

        $this->info("Lembretes enviados: {$sent} (sessões: {$sessions->count()})");

        return self::SUCCESS;
    }
}
