<?php

namespace App\Domain\Queue\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal staff note on a ticket and/or customer. Never shown on customer
 * surfaces or sent by SMS.
 *
 * @property int|null $ticket_id
 * @property int|null $customer_id
 * @property int $author_id
 * @property string $body
 */
class Note extends Model
{
    use BelongsToTenant;

    protected $fillable = ['ticket_id', 'customer_id', 'author_id', 'body'];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
