<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Weekly opening window in the location's local time. department_id null =
 * location hours; set = department hours.
 *
 * @property int $location_id
 * @property int|null $department_id
 * @property int $weekday 0 = Sunday … 6 = Saturday
 * @property string $opens_at HH:MM:SS
 * @property string $closes_at HH:MM:SS
 */
class OpeningHour extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['location_id', 'department_id', 'weekday', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
