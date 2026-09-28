<?php

namespace App\Domain\Signage\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Signage\Concerns\PublishesSignage;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 */
class Playlist extends Model
{
    use Auditable, BelongsToTenant, PublishesSignage;

    protected $fillable = ['name'];

    /** @return BelongsToMany<SignageItem, $this> ordered */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(SignageItem::class, 'playlist_items')
            ->withPivot('id', 'position')->orderByPivot('position');
    }

    /** @return HasMany<PlaylistSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(PlaylistSchedule::class);
    }
}
