<?php

namespace App\Domain\Queue\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per location: the queue lock target and the monotonically
 * increasing queue_version clients use to detect missed updates.
 *
 * @property int $location_id
 * @property int $version
 */
class LocationQueueState extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'location_id';

    public $incrementing = false;

    protected $fillable = ['location_id', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
