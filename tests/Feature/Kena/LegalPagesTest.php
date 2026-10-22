<?php

namespace Tests\Feature\Kena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Ajuda, Termos e Privacidade: públicas e com os prazos reais do sistema. */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_are_public(): void
    {
        $this->get('/ajuda')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/help'));
        $this->get('/termos')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/terms'));
        $this->get('/privacidade')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/privacy'));
    }

    public function test_policy_numbers_come_from_config(): void
    {
        config([
            'kena.hold_minutes' => 12,
            'kena.refund_deadline_hours' => 72,
            'kena.mercadopago.pix_expiration_minutes' => 25,
        ]);

        $this->get('/termos')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('policy.holdMinutes', 12)
            ->where('policy.refundDeadlineHours', 72)
            ->where('policy.pixMinutes', 25));
    }

    public function test_support_contact_and_company_come_from_config(): void
    {
        config([
            'kena.support.email' => 'contato@exemplo.test',
            'kena.support.whatsapp' => '+55 (61) 99999-8888',
            'kena.legal.name' => 'Escola Exemplo',
            'kena.legal.document' => '00.000.000/0001-00',
        ]);

        $this->get('/privacidade')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('support.email', 'contato@exemplo.test')
            ->where('support.whatsapp', '5561999998888')
            ->where('company.name', 'Escola Exemplo')
            ->where('company.document', '00.000.000/0001-00'));
    }

    public function test_missing_contact_is_sent_as_null(): void
    {
        config(['kena.support.email' => null, 'kena.support.whatsapp' => null]);

        $this->get('/ajuda')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('support.email', null)
            ->where('support.whatsapp', null));
    }
}
