<?php

namespace Tests\Feature\Kena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'topsecret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['kena.mercadopago.webhook_secret' => self::SECRET]);
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->postJson('/webhooks/mercadopago',
            ['type' => 'payment', 'id' => 'evt-1', 'data' => ['id' => 'ABC123']],
            ['x-signature' => 'ts=123,v1=deadbeef', 'x-request-id' => 'req-1'],
        )->assertStatus(401);

        $this->assertDatabaseMissing('webhook_events', ['gateway_event_id' => 'evt-1']);
    }

    public function test_accepts_valid_signature(): void
    {
        $ts = '123';
        $requestId = 'req-1';
        $manifest = "id:abc123;request-id:{$requestId};ts:{$ts};"; // data.id em minúsculas
        $v1 = hash_hmac('sha256', $manifest, self::SECRET);

        $this->postJson('/webhooks/mercadopago',
            ['type' => 'payment', 'id' => 'evt-1', 'data' => ['id' => 'ABC123']],
            ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => $requestId],
        )->assertNoContent();

        $this->assertDatabaseHas('webhook_events', ['gateway_event_id' => 'evt-1']);
    }

    public function test_no_secret_skips_verification(): void
    {
        config(['kena.mercadopago.webhook_secret' => null]);

        $this->postJson('/webhooks/mercadopago',
            ['type' => 'payment', 'id' => 'evt-2', 'data' => ['id' => 'X']],
        )->assertNoContent();

        $this->assertDatabaseHas('webhook_events', ['gateway_event_id' => 'evt-2']);
    }
}
