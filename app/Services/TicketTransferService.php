<?php

namespace App\Services;

use App\Exceptions\TransferException;
use App\Mail\TicketTransferredMail;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Support\Codes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Transferência de titularidade. Bloqueada a partir de 24h antes da sessão.
 * O destinatário precisa ter conta; ao transferir, o QR antigo é invalidado e
 * um novo ingresso é emitido para o novo titular.
 */
class TicketTransferService
{
    public function __construct(private readonly QrTokenService $qr) {}

    public function transfer(Ticket $ticket, User $from, string $toEmail): Ticket
    {
        $ticket->loadMissing('session');

        if ($ticket->user_id !== $from->id) {
            throw new TransferException('Você não é o titular deste ingresso.');
        }
        if ($ticket->status !== Ticket::STATUS_VALID) {
            throw new TransferException('Só ingressos válidos podem ser transferidos.');
        }
        if (now()->greaterThanOrEqualTo($ticket->session->transferLocksAt())) {
            throw new TransferException('Transferência encerrada — faltam menos de 24h para a sessão.');
        }

        $email = mb_strtolower(trim($toEmail));
        $recipient = User::where('email', $email)->first();

        if ($recipient !== null && $recipient->id === $from->id) {
            throw new TransferException('Você já é o titular deste ingresso.');
        }

        // Destinatário sem conta: cria uma conta leve (sem senha) e envia o
        // acesso por magic-link no e-mail de transferência.
        if ($recipient === null) {
            $recipient = User::create([
                'email' => $email,
                'name' => $this->nameFromEmail($email),
                'email_verified_at' => now(),
            ]);
        }

        $newTicket = DB::transaction(function () use ($ticket, $from, $recipient, $toEmail): Ticket {
            // Invalidação atômica: só transfere se AINDA estiver VALID — protege
            // contra check-in/reembolso/outra transferência entre a leitura e aqui.
            $invalidated = Ticket::whereKey($ticket->id)
                ->where('status', Ticket::STATUS_VALID)
                ->update(['status' => Ticket::STATUS_TRANSFERRED]);

            if ($invalidated === 0) {
                throw new TransferException('Este ingresso não está mais válido para transferência.');
            }

            $code = Codes::ticket();
            $newTicket = Ticket::create([
                'order_id' => $ticket->order_id,
                'order_item_id' => $ticket->order_item_id,
                'session_id' => $ticket->session_id,
                'user_id' => $recipient->id,
                'session_seat_id' => $ticket->session_seat_id,
                'code' => $code,
                'qr_token' => $this->qr->issue($code),
                'holder_name' => $recipient->name,
                'seat_code' => $ticket->seat_code,
                'sector_name' => $ticket->sector_name,
                'price_cents' => $ticket->price_cents,
                'status' => Ticket::STATUS_VALID,
            ]);

            TicketTransfer::create([
                'ticket_id' => $ticket->id,
                'from_user_id' => $from->id,
                'to_user_id' => $recipient->id,
                'to_email' => $toEmail,
                'status' => TicketTransfer::STATUS_ACCEPTED,
                'new_ticket_id' => $newTicket->id,
                'accepted_at' => now(),
            ]);

            return $newTicket;
        });

        if (filled($recipient->email)) {
            Mail::to($recipient->email)->queue(new TicketTransferredMail($newTicket, $from->name));
        }

        return $newTicket;
    }

    /** Nome legível a partir do e-mail (ex.: "joao.silva@x.com" → "Joao Silva"). */
    private function nameFromEmail(string $email): string
    {
        $local = str_replace(['.', '_', '-'], ' ', explode('@', $email)[0]);
        $name = ucwords(trim($local));

        return $name !== '' ? $name : 'Novo titular';
    }
}
