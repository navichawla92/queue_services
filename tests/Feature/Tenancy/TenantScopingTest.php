<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Exceptions\MissingTenantException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\ProbeItem;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_record_created_in_tenant_context_belongs_to_that_tenant(): void
    {
        [$a] = $this->twoTenants();

        $item = $this->inTenant($a, fn () => ProbeItem::create(['name' => 'svc']));

        $this->assertSame($a->id, $item->tenant_id);
    }

    public function test_write_without_tenant_context_is_rejected_and_persists_nothing(): void
    {
        $this->twoTenants();

        try {
            ProbeItem::create(['name' => 'orphan']);
            $this->fail('Expected MissingTenantException');
        } catch (MissingTenantException) {
        }

        $this->assertDatabaseCount('tenancy_probe_items', 0);
    }

    public function test_cannot_create_record_for_another_tenant(): void
    {
        [$a, $b] = $this->twoTenants();

        $this->expectException(LogicException::class);
        $this->inTenant($a, fn () => ProbeItem::create(['name' => 'x', 'tenant_id' => $b->id]));
    }

    public function test_tenant_id_cannot_be_changed(): void
    {
        [$a, $b] = $this->twoTenants();
        $item = $this->inTenant($a, fn () => ProbeItem::create(['name' => 'x']));

        $this->expectException(LogicException::class);
        $this->inTenant($a, fn () => $item->forceFill(['tenant_id' => $b->id])->save());
    }

    public function test_queries_only_see_current_tenant_and_fail_closed_without_tenant(): void
    {
        [$a, $b] = $this->twoTenants();
        $this->inTenant($a, fn () => ProbeItem::create(['name' => 'a1']));
        $bItem = $this->inTenant($b, fn () => ProbeItem::create(['name' => 'b1']));

        $this->assertSame(['a1'], $this->inTenant($a, fn () => ProbeItem::pluck('name')->all()));
        $this->assertNull($this->inTenant($a, fn () => ProbeItem::find($bItem->id)));
        $this->assertSame(0, ProbeItem::count(), 'No tenant bound must match nothing');
        $this->assertSame(2, ProbeItem::withoutTenantScope()->count());
    }

    public function test_cross_tenant_route_model_binding_returns_not_found(): void
    {
        Route::middleware(['web', 'auth', 'tenant.user'])
            ->get('/_probe/{item}', fn (ProbeItem $item) => ['name' => $item->name]);

        [$a, $b] = $this->twoTenants();
        $aItem = $this->inTenant($a, fn () => ProbeItem::create(['name' => 'mine']));
        $bItem = $this->inTenant($b, fn () => ProbeItem::create(['name' => 'theirs']));
        $userA = $this->userIn($a);

        $this->actingAs($userA)->getJson("/_probe/{$aItem->id}")->assertOk()->assertJson(['name' => 'mine']);
        $this->assertCrossTenantHidden($userA, "/_probe/{$bItem->id}");
    }
}
