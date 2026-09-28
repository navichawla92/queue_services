<?php

namespace App\Domain\Feedback\Models;

use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $customer_id
 * @property int $location_id
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property-read Ticket $ticket
 * @property-read Location $location
 */
class FeedbackRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['ticket_id', 'customer_id', 'location_id', 'token', 'expires_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasOne<FeedbackResponse, $this> */
    public function response(): HasOne
    {
        return $this->hasOne(FeedbackResponse::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function url(): string
    {
        return route('public.feedback', $this->token);
    }
}
