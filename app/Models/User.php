<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\Models\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

/**
 * Staff login identity. Not tenant-scoped by global scope (sign-in happens
 * before a tenant is known); tenant membership is enforced by middleware and
 * policies. Platform admins have no tenant.
 *
 * @property int|null $tenant_id
 * @property bool $is_platform_admin
 * @property bool $is_active
 * @property bool $all_locations
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Mirrors the column defaults so new instances are complete before refresh. */
    protected $attributes = [
        'is_platform_admin' => false,
        'is_active' => true,
        'all_locations' => false,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
            'all_locations' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<Location, $this> assigned locations (see LocationAccess) */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    /** @return HasOne<Employee, $this> serving profile (staff who serve customers) */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** Active account, and (unless a platform admin) an active tenant. */
    public function canSignIn(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->tenant_id === null) {
            return $this->is_platform_admin;
        }

        return (bool) $this->tenant?->isActive();
    }

    /** Deactivate and end every existing session for this user. */
    public function deactivate(): void
    {
        $this->forceFill(['is_active' => false, 'remember_token' => null])->save();

        // Database-driver sessions are removed now; any other driver is caught
        // on the next request by ResolveTenantFromUser (is_active check).
        DB::table(config('session.table') ?: 'sessions')->where('user_id', $this->id)->delete();
    }

    public function reactivate(): void
    {
        $this->forceFill(['is_active' => true])->save();
    }
}
