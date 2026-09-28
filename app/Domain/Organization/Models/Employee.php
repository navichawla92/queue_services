<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Serving profile of a staff user. Work locations are the user's assigned
 * locations (location_user); departments and skills are per employee.
 *
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property int|null $default_desk_id
 * @property EmployeeStatus $status
 * @property int|null $current_location_id
 * @property int|null $current_desk_id
 * @property Carbon|null $status_changed_at
 * @property-read User $user
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /** Live status is operational, not setup; not audited per change. */
    protected static array $auditIgnore = ['status', 'status_changed_at', 'current_location_id', 'current_desk_id'];

    protected $attributes = ['status' => 'offline'];

    protected $fillable = ['user_id', 'display_name', 'default_desk_id'];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Department, $this> */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /** @return BelongsTo<Desk, $this> */
    public function defaultDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'default_desk_id');
    }

    /** @return BelongsTo<Desk, $this> */
    public function currentDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'current_desk_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function isActive(): bool
    {
        return $this->user->is_active;
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }
}
