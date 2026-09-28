<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\ProbeItem;
use Tests\TestCase;

class TenantJobContextTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_queued_job_runs_in_dispatching_tenant_and_only_sees_its_data(): void
    {
        [$a, $b] = $this->twoTenants();
        $this->inTenant($a, fn () => ProbeItem::create(['name' => 'a-only']));
        $this->inTenant($b, fn () => ProbeItem::create(['name' => 'b-only']));

        RecordTenantJob::$seen = null;
        // Statement closure: PendingDispatch dispatches on destruct, which must
        // happen while tenant B is still bound.
        $this->inTenant($b, function () {
            RecordTenantJob::dispatch();
        });

        $this->assertSame(['tenant' => $b->id, 'names' => ['b-only']], RecordTenantJob::$seen);
    }

    public function test_caller_context_is_restored_after_sync_job(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);

        RecordTenantJob::dispatch();

        $this->assertSame($a->id, app(TenantContext::class)->id());
    }

    public function test_each_active_iterates_only_active_tenants(): void
    {
        [$a, $b] = $this->twoTenants();
        $b->suspend();

        $seen = [];
        app(TenantContext::class)->eachActive(function ($tenant) use (&$seen) {
            $seen[] = app(TenantContext::class)->id();
        });

        $this->assertSame([$a->id], $seen);
        $this->assertNull(app(TenantContext::class)->id());
    }
}

class RecordTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var array{tenant: int|null, names: list<string>}|null */
    public static ?array $seen = null;

    public function handle(TenantContext $context): void
    {
        self::$seen = ['tenant' => $context->id(), 'names' => ProbeItem::pluck('name')->all()];
    }
}
