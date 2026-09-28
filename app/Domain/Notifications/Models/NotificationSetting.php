<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $location_id
 * @property string $event
 * @property bool $enabled
 */
class NotificationSetting extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['location_id', 'event', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
