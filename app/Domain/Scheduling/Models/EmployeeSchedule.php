<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $employee_id
 * @property int $location_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 */
class EmployeeSchedule extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['employee_id', 'location_id', 'weekday', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
