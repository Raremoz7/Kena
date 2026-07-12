<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\EventSession;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Check-in: por QR (assinatura + sessão + estado) ou por admissão manual
 * (busca por nome/código). Marca o ingresso como utilizado e registra a leitura.
 */
class CheckInService
{
    public function __construct(private readonly QrTokenService $qr) {}

    /**
     * Check-in por QR.
     *
     * @return array{result: string, reason: ?string, ticket: ?array<string, mixed>, progress: array{checkedIn: int, total: int}}
     */
    public function check(string $token, EventSession $session, ?User $operator): array
    {
        if (! $this->qr->verify($token)) {
            return $this->record($session, $operator, CheckIn::RESULT_DENIED, 'QR inválido.', null, mb_substr($token, 0, 40));
        }

        $ticket = Ticket::where('qr_token', $token)->first();
        if ($ticket === null) {
            return $this->record($session, $operator, CheckIn::RESULT_DENIED, 'Ingresso não encontrado.', null, mb_substr($token, 0, 40));
        }

        return $this->admitOrDeny($ticket, $session, $operator);
    }

    /**
     * Admissão manual de um ingresso já localizado (busca por nome/código).
     *
     * @return array{result: string, reason: ?string, ticket: ?array<string, mixed>, progress: array{checkedIn: int, total: int}}
     */
    public function admit(Ticket $ticket, EventSession $session, ?User $operator): array
    {
        return $this->admitOrDeny($ticket, $session, $operator);
    }

    /** @return array{checkedIn: int, total: int} */
    public function progress(EventSession $session): array
    {
        return $this->progressForSessions(collect([$session]))[$session->id];
    }

    /**
     * Progresso de várias sessões de uma vez (2 queries agregadas em vez de
     * 2 por sessão) — usado no seletor de sessões da tela de check-in.
     *
     * @param  \Illuminate\Support\Collection<int, EventSession>  $sessions
     * @return array<int, array{checkedIn: int, total: int}>
     */
    public function progressForSessions($sessions): array
    {
        $ids = $sessions->pluck('id');

        $totals = Ticket::whereIn('session_id', $ids)
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->selectRaw('session_id, count(*) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id');
        $checkedIn = Ticket::whereIn('session_id', $ids)
            ->where('status', Ticket::STATUS_USED)
            ->selectRaw('session_id, count(*) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id');

        return $ids->mapWithKeys(fn ($id): array => [$id => [
            'checkedIn' => (int) ($checkedIn[$id] ?? 0),
            'total' => (int) ($totals[$id] ?? 0),
        ]])->all();
    }

    /**
     * @return array{result: string, reason: ?string, ticket: ?array<string, mixed>, progress: array{checkedIn: int, total: int}}
     */
    private function admitOrDeny(Ticket $ticket, EventSession $session, ?User $operator): array
    {
        [$result, $reason] = $this->evaluate($ticket, $session);

        return $this->record($session, $operator, $result, $reason, $ticket, $ticket->code);
    }

    /** @return array{0: string, 1: ?string} */
    private function evaluate(Ticket $ticket, EventSession $session): array
    {
        if ($ticket->session_id !== $session->id) {
            return [CheckIn::RESULT_DENIED, 'Ingresso de outra sessão.'];
        }

        // Admissão atômica: só marca USED se ainda estiver VALID. Dois leitores
        // escaneando o mesmo QR ao mesmo tempo → só o primeiro admite.
        $admitted = DB::transaction(fn (): int => Ticket::whereKey($ticket->id)
            ->where('status', Ticket::STATUS_VALID)
            ->update(['status' => Ticket::STATUS_USED, 'checked_in_at' => now()]));

        if ($admitted === 1) {
            $ticket->refresh();

            return [CheckIn::RESULT_OK, null];
        }

        // Perdeu a corrida (ou nunca foi válido): relê o estado real pra negar com motivo.
        $ticket->refresh();

        if ($ticket->status === Ticket::STATUS_USED) {
            $when = $ticket->checked_in_at?->format('d/m H:i');

            return [CheckIn::RESULT_DENIED, 'Ingresso já utilizado'.($when ? " ({$when})" : '').'.'];
        }

        return [CheckIn::RESULT_DENIED, 'Ingresso '.$ticket->status.'.'];
    }

    /**
     * @return array{result: string, reason: ?string, ticket: ?array<string, mixed>, progress: array{checkedIn: int, total: int}}
     */
    private function record(EventSession $session, ?User $operator, string $result, ?string $reason, ?Ticket $ticket, string $scanned): array
    {
        CheckIn::create([
            'ticket_id' => $ticket?->id,
            'session_id' => $session->id,
            'operator_id' => $operator?->id,
            'result' => $result,
            'reason' => $reason,
            'scanned_code' => $scanned,
            'scanned_at' => now(),
        ]);

        return [
            'result' => $result,
            'reason' => $reason,
            'ticket' => $ticket !== null ? $this->ticketPayload($ticket) : null,
            'progress' => $this->progress($session),
        ];
    }

    /** @return array<string, mixed> */
    private function ticketPayload(Ticket $ticket): array
    {
        return [
            'code' => $ticket->code,
            'holderName' => $ticket->holder_name,
            'sectorName' => $ticket->sector_name,
            'seatLabel' => $ticket->seat_code,
            'checkedInAt' => $ticket->checked_in_at?->toIso8601String(),
        ];
    }
}
