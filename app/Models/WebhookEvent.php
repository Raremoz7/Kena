<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $gateway
 * @property string $gateway_event_id
 * @property string|null $type
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
