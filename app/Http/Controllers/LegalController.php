<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajuda, termos de uso e política de privacidade. Os prazos citados nos textos
 * vêm da mesma config que o sistema aplica, para o que está escrito nunca
 * divergir do comportamento real.
 */
class LegalController extends Controller
{
    public function help(): Response
    {
        return Inertia::render('buyer/help', $this->props());
    }

    public function terms(): Response
    {
        return Inertia::render('buyer/terms', $this->props());
    }

    public function privacy(): Response
    {
        return Inertia::render('buyer/privacy', $this->props());
    }

    /** @return array<string, mixed> */
    private function props(): array
    {
        $whatsapp = (string) preg_replace('/\D/', '', (string) config('kena.support.whatsapp'));
        $maxTickets = config('kena.max_tickets_per_buyer');

        return [
            'company' => [
                'name' => (string) config('kena.legal.name'),
                'document' => config('kena.legal.document') ?: null,
            ],
            'support' => [
                'email' => config('kena.support.email') ?: null,
                'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            ],
            'policy' => [
                'holdMinutes' => (int) config('kena.hold_minutes', 10),
                'pixMinutes' => (int) config('kena.mercadopago.pix_expiration_minutes', 30),
                'refundDeadlineHours' => (int) config('kena.refund_deadline_hours', 48),
                'maxTickets' => $maxTickets !== null ? (int) $maxTickets : null,
            ],
            'updatedAt' => (string) config('kena.legal.updated_at'),
        ];
    }
}
