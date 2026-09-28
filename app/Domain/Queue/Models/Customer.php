<?php

namespace App\Domain\Queue\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer of one tenant, matched by E.164 phone on check-in.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property int $visit_count
 * @property int $no_show_count
 * @property Carbon|null $last_visit_at
 * @property Carbon|null $anonymized_at
 */
class Customer extends Model
{
    use BelongsToTenant;

    protected $attributes = ['visit_count' => 0, 'no_show_count' => 0];

    protected $fillable = ['name', 'phone', 'email'];

    protected function casts(): array
    {
        return ['last_visit_at' => 'datetime', 'anonymized_at' => 'datetime'];
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
