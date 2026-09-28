<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $local_date
 * @property int $location_id
 * @property int $department_id
 * @property int $service_id
 * @property int $employee_id
 * @property string $customer_type
 * @property list<int> $checkins_by_hour
 */
class DailyStat extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['local_date' => 'date', 'checkins_by_hour' => 'array'];
    }
}
