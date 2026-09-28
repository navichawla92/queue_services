<?php

namespace App\Domain\Routing\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\CustomerType;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "When [service] [customer type] on [days] between [times] → department
 * (+priority)". Null criteria match anything. First active match wins.
 *
 * @property int $location_id
 * @property int $sort_order
 * @property int|null $service_id
 * @property string|null $customer_type
 * @property list<int>|null $weekdays
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property int $department_id
 * @property int $priority
 * @property bool $is_active
 */
class RoutingRule extends Model
{
    use Auditable, BelongsToTenant;

    protected $attributes = ['sort_order' => 0, 'priority' => 0, 'is_active' => true];

    protected $fillable = ['location_id', 'sort_order', 'service_id', 'customer_type', 'weekdays', 'starts_at', 'ends_at', 'department_id', 'priority', 'is_active'];

    protected function casts(): array
    {
        return ['weekdays' => 'array', 'sort_order' => 'integer', 'priority' => 'integer', 'is_active' => 'boolean'];
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

    /** @param  CarbonInterface  $localTime  time in the location's time zone */
    public function matches(Service $service, CustomerType $type, CarbonInterface $localTime): bool
    {
        if ($this->service_id !== null && $this->service_id !== $service->id) {
            return false;
        }
        if ($this->customer_type !== null && $this->customer_type !== $type->value) {
            return false;
        }
        if ($this->weekdays !== null && $this->weekdays !== [] && ! in_array($localTime->dayOfWeek, array_map('intval', $this->weekdays), true)) {
            return false;
        }

        $now = $localTime->format('H:i:s');
        if ($this->starts_at !== null && $now < $this->normalize($this->starts_at)) {
            return false;
        }
        if ($this->ends_at !== null && $now >= $this->normalize($this->ends_at)) {
            return false;
        }

        return true;
    }

    private function normalize(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
