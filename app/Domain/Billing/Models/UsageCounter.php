<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $metric
 * @property string $period
 * @property int $value
 * @property bool $warned_80
 * @property bool $warned_100
 */
class UsageCounter extends Model
{
    use BelongsToTenant;

    protected $fillable = ['metric', 'period', 'value', 'warned_80', 'warned_100'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'warned_80' => 'boolean', 'warned_100' => 'boolean'];
    }
}
