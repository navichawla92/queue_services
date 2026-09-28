<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Access\SupportSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->root = User::factory()->create(['name' => 'Root']);
        $this->root->forceFill(['is_platform_admin' => true])->save();
    }

    public function test_only_platform_admins_reach_the_platform_console(): void
    {
        [$a] = $this->twoTenants();

        $this->actingAs($this->root)->get('/platform')->assertOk()->assertSee('Tenant A');
        $this->actingAs($this->userIn($a))->get('/platform')->assertForbidden();
    }

    public function test_platform_admin_without_support_session_cannot_open_tenant_surfaces(): void
    {
        $this->twoTenants();

        $this->actingAs($this->root)->get('/admin')->assertForbidden();
    }

    public function test_support_session_requires_reason_is_audited_and_shows_banner(): void
    {
        [$a] = $this->twoTenants();

        $this->actingAs($this->root)->post("/platform/tenants/{$a->id}/support", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->root)->post("/platform/tenants/{$a->id}/support", ['reason' => 'Ticket #123 queue issue'])
            ->assertRedirect('/admin');

        $entry = AuditLog::withoutGlobalScopes()->where('action', 'platform.support_session_started')->sole();
        $this->assertSame($a->id, $entry->tenant_id);
        $this->assertSame('Root', $entry->actor_name);
        $this->assertSame('Ticket #123 queue issue', $entry->meta['reason']);

        $this->get('/admin')->assertOk()
            ->assertSee('support-banner', false)
            ->assertSee('Ticket #123 queue issue');
        $this->get('/admin/audit')->assertOk();
    }

    public function test_ending_support_session_is_audited_and_removes_access(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAs($this->root)->post("/platform/tenants/{$a->id}/support", ['reason' => 'Checking setup']);

        $this->delete('/platform/support')->assertRedirect('/platform');

        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'platform.support_session_ended')->where('tenant_id', $a->id)->exists());
        $this->assertNull(session(SupportSession::TENANT_KEY));
        $this->get('/admin')->assertForbidden();
    }

    public function test_tenant_user_cannot_hijack_support_session_key(): void
    {
        [$a, $b] = $this->twoTenants();
        $user = $this->userIn($a);

        $this->actingAs($user)->withSession([SupportSession::TENANT_KEY => $b->id])->get('/staff');

        $this->assertSame($a->id, $this->tenantContext()->id());
    }
}
