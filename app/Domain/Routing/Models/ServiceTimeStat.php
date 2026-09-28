<?php

namespace App\Domain\Routing\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $location_id
 * @property int $service_id
 * @property int $avg_seconds
 * @property int $sample_count
 */
class ServiceTimeStat extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['location_id', 'service_id', 'avg_seconds', 'sample_count', 'computed_at'];

    protected function casts(): array
    {
        return ['computed_at' => 'datetime', 'avg_seconds' => 'integer', 'sample_count' => 'integer'];
    }
}
