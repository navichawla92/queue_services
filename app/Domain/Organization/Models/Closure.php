<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Closure or special hours on a date (local). Both times null = closed all
 * day; department_id null = whole location.
 *
 * @property int $location_id
 * @property int|null $department_id
 * @property Carbon $date
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property string|null $reason
 */
class Closure extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['location_id', 'department_id', 'date', 'opens_at', 'closes_at', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function isClosedAllDay(): bool
    {
        return $this->opens_at === null || $this->closes_at === null;
    }
}
