<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $ticket_id
 * @property int $session_id
 * @property int|null $operator_id
 * @property string $result
 * @property string|null $reason
 * @property string|null $scanned_code
 * @property Carbon $scanned_at
 */
class CheckIn extends Model
{
    use ScopedThroughParent;

    public const RESULT_OK = 'ok';

    public const RESULT_DENIED = 'denied';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function organizationParent(): string
    {
        return 'session';
    }

    /** @return BelongsTo<EventSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }
}
