<?php

namespace App\Domain\Signage\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Signage\Concerns\PublishesSignage;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int|null $location_id
 * @property string $body
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 * @property int $sort_order
 */
class TickerMessage extends Model
{
    use Auditable, BelongsToTenant, PublishesSignage;

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected $fillable = ['location_id', 'body', 'starts_on', 'ends_on', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
