<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'tenant.public:tenant'])
            ->get('/_public/{tenant}', fn () => 'tenant='.app(TenantContext::class)->id());

        Route::middleware(['web', 'auth', 'tenant.user'])
            ->get('/_staff', fn () => 'tenant='.app(TenantContext::class)->id());
        if (! Route::has('login')) {
            Route::get('/login', fn () => 'login')->name('login');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_public_identifier_resolves_tenant(): void
    {
        [$a] = $this->twoTenants();

        $this->get("/_public/{$a->public_id}")->assertOk()->assertSee("tenant={$a->id}");
    }

    public function test_unknown_public_identifier_is_generic_not_found(): void
    {
        $this->twoTenants();

        $this->get('/_public/01jzzzzzzzzzzzzzzzzzzzzzzz')->assertNotFound();
    }

    public function test_suspended_tenant_public_surface_shows_unavailable(): void
    {
        [$a] = $this->twoTenants();
        $a->suspend();

        $this->get("/_public/{$a->public_id}")
            ->assertStatus(503)
            ->assertSee('Service unavailable');
    }

    public function test_authenticated_user_binds_their_tenant(): void
    {
        [, $b] = $this->twoTenants();

        $this->actingAs($this->userIn($b))->get('/_staff')->assertOk()->assertSee("tenant={$b->id}");
    }

    public function test_user_of_suspended_tenant_is_signed_out(): void
    {
        [$a] = $this->twoTenants();
        $user = $this->userIn($a);
        $a->suspend();

        $this->actingAs($user)->get('/_staff')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_deactivated_user_is_signed_out(): void
    {
        [$a] = $this->twoTenants();
        $user = $this->userIn($a);
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/_staff')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_suspend_and_reactivate(): void
    {
        [$a] = $this->twoTenants();

        $a->suspend();
        $this->assertFalse($a->fresh()->isActive());
        $this->assertNotNull($a->fresh()->suspended_at);

        $a->reactivate();
        $this->assertTrue($a->fresh()->isActive());
    }
}
