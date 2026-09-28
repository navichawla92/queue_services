<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $location_id
 * @property string $provider
 * @property string|null $account_sid
 * @property string|null $auth_token encrypted
 * @property string|null $from_number
 * @property string|null $messaging_service_sid
 * @property string|null $help_message
 * @property string $quiet_start
 * @property string $quiet_end
 * @property int $wait_update_threshold
 * @property int $position_alert_at
 */
class SmsSetting extends Model
{
    use Auditable, BelongsToTenant;

    protected $attributes = [
        'provider' => 'log', 'quiet_start' => '21:00:00', 'quiet_end' => '08:00:00',
        'wait_update_threshold' => 10, 'position_alert_at' => 3,
    ];

    protected $fillable = [
        'location_id', 'provider', 'account_sid', 'auth_token', 'from_number', 'messaging_service_sid',
        'help_message', 'quiet_start', 'quiet_end', 'wait_update_threshold', 'position_alert_at',
    ];

    protected $hidden = ['auth_token'];

    protected function casts(): array
    {
        return [
            'auth_token' => 'encrypted',
            'wait_update_threshold' => 'integer',
            'position_alert_at' => 'integer',
        ];
    }
}
