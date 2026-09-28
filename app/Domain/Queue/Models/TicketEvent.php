<?php

namespace App\Domain\Queue\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only ticket history.
 *
 * @property int $ticket_id
 * @property string $type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string $actor_type
 * @property int|null $actor_id
 * @property int|null $employee_id
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class TicketEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
