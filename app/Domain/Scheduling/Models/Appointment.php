<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Models\Customer;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Scheduling\AppointmentStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $location_id
 * @property int $service_id
 * @property int|null $employee_id
 * @property int|null $customer_id
 * @property int|null $ticket_id
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property bool $sms_consent
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property AppointmentStatus $status
 * @property string $source
 * @property string $confirmation_code
 * @property string $manage_token
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $cancelled_at
 * @property int $reschedule_count
 * @property-read Location $location
 * @property-read Service $service
 * @property-read Employee|null $employee
 */
class Appointment extends Model
{
    use Auditable, BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['manage_token'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'sms_consent' => 'boolean',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::slotValues());
    }

    public function localStart(): Carbon
    {
        return $this->starts_at->copy()->setTimezone($this->location->effectiveTimezone());
    }

    public function manageUrl(): string
    {
        return route('public.appointment', $this->manage_token);
    }

    public function checkinUrl(): string
    {
        return route('public.appointment.checkin', $this->manage_token);
    }
}
