<?php

namespace Tests\Feature\Access;

use App\Domain\Access\AuditLogger;
use App\Domain\Access\Models\AuditLog;
use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Location;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_sign_in_and_sign_out_are_audited_for_the_users_tenant(): void
    {
        [$a] = $this->twoTenants();
        $this->userIn($a, ['email' => 'ann@example.com', 'name' => 'Ann']);

        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password']);
        $this->post('/logout');

        $actions = AuditLog::withoutGlobalScopes()->where('tenant_id', $a->id)->pluck('action')->all();
        $this->assertContains('auth.sign_in', $actions);
        $this->assertContains('auth.sign_out', $actions);
    }

    public function test_setup_changes_record_actor_and_before_after_values(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $admin = $this->userIn($a, ['name' => 'Ada']);
        $this->actingAs($admin);

        $location = Location::factory()->create(['name' => 'Main']);
        $location->update(['name' => 'Main Street']);

        $entry = AuditLog::query()->where('action', 'location.updated')->sole();
        $this->assertSame(['name' => 'Main'], $entry->before);
        $this->assertSame(['name' => 'Main Street'], $entry->after);
        $this->assertSame('Ada', $entry->actor_name);
        $this->assertSame($a->id, $entry->tenant_id);
        $this->assertSame($location->id, $entry->location_id);
    }

    public function test_role_changes_are_audited(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $user = $this->userIn($a);

        $user->assignRole(Roles::RECEPTIONIST);
        $user->removeRole(Roles::RECEPTIONIST);

        $this->assertSame(['receptionist'], AuditLog::query()->where('action', 'user.role_attached')->sole()->after['roles']);
        $this->assertTrue(AuditLog::query()->where('action', 'user.role_detached')->exists());
    }

    public function test_entries_are_append_only(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $entry = app(AuditLogger::class)->log('test.event');

        $this->expectException(LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    public function test_entries_cannot_be_deleted(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $entry = app(AuditLogger::class)->log('test.event');

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_viewer_is_admin_only_and_shows_only_own_tenant(): void
    {
        [$a, $b] = $this->twoTenants();
        $this->inTenant($a, fn () => app(AuditLogger::class)->log('mine.event'));
        $this->inTenant($b, fn () => app(AuditLogger::class)->log('theirs.event'));

        $admin = $this->inTenant($a, function () use ($a) {
            $u = $this->userIn($a);
            $u->assignRole(Roles::COMPANY_ADMIN);

            return $u;
        });
        $employee = $this->inTenant($a, function () use ($a) {
            $u = $this->userIn($a);
            $u->assignRole(Roles::EMPLOYEE);

            return $u;
        });

        $this->actingAs($admin)->get('/admin/audit')->assertOk()->assertSee('mine.event')->assertDontSee('theirs.event');
        $this->actingAs($employee)->get('/admin/audit')->assertForbidden();
    }
}
