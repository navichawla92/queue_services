<?php

namespace App\Domain\Queue\Models;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A place in a location's queue. Mutated only through TicketStateMachine
 * (under the location queue lock); history in ticket_events.
 *
 * @property int $id
 * @property int $location_id
 * @property int $department_id
 * @property int $service_id
 * @property int|null $customer_id
 * @property int|null $appointment_id
 * @property string $number
 * @property int $sequence
 * @property Carbon $local_date
 * @property CheckinChannel $channel
 * @property CustomerType $customer_type
 * @property TicketStatus $status
 * @property int $priority
 * @property string $public_token
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property bool $sms_consent
 * @property int|null $assigned_employee_id
 * @property int|null $serving_employee_id
 * @property int|null $desk_id
 * @property Carbon $checked_in_at
 * @property Carbon $queued_at
 * @property Carbon|null $first_called_at
 * @property Carbon|null $called_at
 * @property Carbon|null $service_started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $hold_started_at
 * @property int $total_hold_seconds
 * @property int|null $wait_seconds
 * @property int|null $service_seconds
 * @property int $recall_count
 * @property int $transfer_count
 * @property int|null $estimated_wait_minutes
 * @property int $initial_ahead
 * @property int|null $notified_wait_minutes
 * @property string|null $hold_reason
 * @property string|null $outcome
 * @property string|null $closed_reason
 */
class Ticket extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'channel' => CheckinChannel::class,
            'customer_type' => CustomerType::class,
            'status' => TicketStatus::class,
            'priority' => 'integer',
            'sms_consent' => 'boolean',
            'checked_in_at' => 'datetime',
            'queued_at' => 'datetime',
            'first_called_at' => 'datetime',
            'called_at' => 'datetime',
            'service_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'hold_started_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function servingEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'serving_employee_id');
    }

    /** @return BelongsTo<Desk, $this> */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    /** @return HasMany<TicketEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TicketStatus::activeValues());
    }

    /** First name + last initial, for opt-in lobby display (never the full name). */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/', trim($this->customer_name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1).'.' : '';

        return trim($first.' '.$last);
    }

    /** Current waiting time in seconds (live), excluding hold time. */
    public function currentWaitSeconds(?Carbon $now = null): int
    {
        $now ??= now();
        $end = $this->first_called_at ?? $now;
        $hold = $this->total_hold_seconds + ($this->hold_started_at ? (int) $this->hold_started_at->diffInSeconds($now) : 0);

        return max(0, (int) $this->checked_in_at->diffInSeconds($end) - $hold);
    }
}
