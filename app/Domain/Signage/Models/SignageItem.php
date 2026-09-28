<?php

namespace App\Domain\Signage\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Signage\Concerns\PublishesSignage;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property string|null $media_path
 * @property string|null $media_mime
 * @property int|null $media_size
 * @property int|null $duration_seconds
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 */
class SignageItem extends Model
{
    use Auditable, BelongsToTenant, PublishesSignage;

    public const TYPES = ['announcement', 'image', 'video', 'qr', 'service_info', 'rich_text'];

    public const DEFAULT_DURATION = 10;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['type', 'title', 'body', 'url', 'media_path', 'media_mime', 'media_size', 'duration_seconds', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean', 'duration_seconds' => 'integer'];
    }

    /** Active and within its date range on the given local date (Y-m-d). */
    public function isLiveOn(string $date): bool
    {
        return $this->is_active
            && ($this->starts_on === null || $this->starts_on->format('Y-m-d') <= $date)
            && ($this->ends_on === null || $this->ends_on->format('Y-m-d') >= $date);
    }
}
