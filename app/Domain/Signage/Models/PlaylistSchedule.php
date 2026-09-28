<?php

namespace App\Domain\Signage\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Signage\Concerns\PublishesSignage;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $playlist_id
 * @property int|null $location_id
 * @property int|null $device_id
 * @property bool $is_default
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property list<int>|null $weekdays
 * @property string|null $start_time
 * @property string|null $end_time
 */
class PlaylistSchedule extends Model
{
    use Auditable, BelongsToTenant, PublishesSignage;

    protected $attributes = ['is_default' => false];

    protected $fillable = ['playlist_id', 'location_id', 'device_id', 'is_default', 'starts_on', 'ends_on', 'weekdays', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'weekdays' => 'array'];
    }

    /** @return BelongsTo<Playlist, $this> */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /** @param  CarbonInterface  $local  now in the display's location time zone */
    public function isActiveAt(CarbonInterface $local): bool
    {
        $date = $local->format('Y-m-d');
        $time = $local->format('H:i:s');

        return ($this->starts_on === null || $this->starts_on->format('Y-m-d') <= $date)
            && ($this->ends_on === null || $this->ends_on->format('Y-m-d') >= $date)
            && ($this->weekdays === null || $this->weekdays === [] || in_array($local->dayOfWeek, array_map('intval', $this->weekdays), true))
            && ($this->start_time === null || $time >= $this->start_time)
            && ($this->end_time === null || $time < $this->end_time);
    }

    /** Higher = more specific (target first, then each narrowing condition). */
    public function specificity(): int
    {
        return ($this->device_id ? 8 : ($this->location_id ? 4 : 0))
            + ($this->start_time ? 1 : 0)
            + ($this->weekdays ? 1 : 0)
            + ($this->starts_on || $this->ends_on ? 2 : 0);
    }
}
