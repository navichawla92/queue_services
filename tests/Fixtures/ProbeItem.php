<?php

namespace Tests\Fixtures;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Test-only tenant-owned model. */
class ProbeItem extends Model
{
    use BelongsToTenant;

    protected $table = 'tenancy_probe_items';

    protected $fillable = ['name', 'tenant_id'];
}
