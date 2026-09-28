<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $event
 * @property string $locale
 * @property string $body
 */
class SmsTemplate extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['event', 'locale', 'body'];
}
