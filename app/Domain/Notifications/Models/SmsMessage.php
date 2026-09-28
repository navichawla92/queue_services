<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\NotificationEvent;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SMS log entry (sms-notifications "Delivery tracking").
 *
 * @property int $id
 * @property int|null $location_id
 * @property int|null $ticket_id
 * @property int|null $appointment_id
 * @property int|null $customer_id
 * @property string $to
 * @property NotificationEvent $event
 * @property string $body
 * @property string $status
 * @property string|null $status_reason
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property int $segments
 * @property string|null $price
 * @property int $attempts
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 */
class SmsMessage extends Model
{
    use BelongsToTenant;

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public const UNDELIVERED = 'undelivered';

    public const SUPPRESSED = 'suppressed';

    public const SKIPPED = 'skipped';

    public const STATUSES = [self::QUEUED, self::SENT, self::DELIVERED, self::FAILED, self::UNDELIVERED, self::SUPPRESSED, self::SKIPPED];

    protected $guarded = ['id', 'tenant_id'];

    protected $attributes = ['segments' => 1, 'attempts' => 0];

    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
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
}
