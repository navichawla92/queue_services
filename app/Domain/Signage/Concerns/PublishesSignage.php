<?php

namespace App\Domain\Signage\Concerns;

use App\Domain\Signage\SignagePublisher;
use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait PublishesSignage
{
    public static function bootPublishesSignage(): void
    {
        static::saved(fn () => app(SignagePublisher::class)->changed());
        static::deleted(fn () => app(SignagePublisher::class)->changed());
    }
}
