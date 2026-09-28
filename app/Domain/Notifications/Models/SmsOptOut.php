<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $phone
 * @property string $source
 */
class SmsOptOut extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['phone', 'source', 'opted_out_at'];

    protected function casts(): array
    {
        return ['opted_out_at' => 'datetime'];
    }
}
