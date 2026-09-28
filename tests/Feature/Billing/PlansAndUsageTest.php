<?php

namespace Tests\Feature\Billing;

use App\Domain\Access\DevicePairingService;
use App\Domain\Access\Models\AuditLog;
use App\Domain\Access\Roles;
use App\Domain\Billing\Notifications\UsageThresholdReached;
use App\Domain\Billing\Usage;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsSetting;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\Admin\Setup\Locations;
use App\Livewire\Platform\TenantsConsole;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PlansAndUsageTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PlansSeeder::class]);
        $this->tenant = $this->createTenant(['name' => 'Acme'], Plan::query()->where('code', 'starter')->sole());
        $this->createTenant(['name' => 'Other']);
        $this->buildQueue($this->tenant);
        $this->admin = $this->userIn($this->tenant);
        $this->admin->assignRole(Roles::COMPANY_ADMIN);
    }

    public function test_gated_features_are_refused_server_side_with_upgrade_message(): void
    {
        $this->actingAs($this->admin);
        $this->tenantContext()->clear();

        $this->get('/admin/signage')->assertForbidden()->assertSee('available on higher plans');
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/staff/appointments')->assertForbidden();
        $this->get('/admin')->assertOk()->assertSee('data-testid="gated-signage"', false);
        $this->get('/admin/feedback')->assertOk(); // Starter includes feedback

        // Public visitors (not signed in) simply don't see gated pages.
        auth()->logout();
        $this->tenantContext()->clear();
        $this->get('/book/'.$this->location->checkin_public_id)->assertNotFound();
    }

    public function test_location_limit_is_enforced(): void
    {
        $this->actingAs($this->admin);
        $this->actingAsTenant($this->tenant);

        Livewire::test(Locations::class)->call('create')->set('name', 'Second')->call('save')
            ->assertHasErrors('name')->assertSee('Your plan allows 1 locations');
        $this->assertSame(1, Location::query()->count());
    }

    public function test_display_limit_is_enforced(): void
    {
        $pair = function () {
            $p = $this->postJson('/devices/pairings', ['type' => 'display'])->json();

            return $this->inTenant($this->tenant, fn () => app(DevicePairingService::class)->claim($p['code'], $this->location, 'TV'));
        };
        $pair();
        $pair();

        $this->expectException(ValidationException::class);
        $pair();
    }

    public function test_sms_blocked_at_allowance_with_block_policy_and_warnings_sent_once(): void
    {
        Notification::fake();
        SmsSetting::create(['provider' => 'log']);
        $usage = app(Usage::class);

        $usage->increment('sms_segments', 799);
        Notification::assertNothingSent();
        $usage->increment('sms_segments', 1);          // 80%
        $usage->increment('sms_segments', 1);          // still 80%: no second email
        Notification::assertSentToTimes($this->admin, UsageThresholdReached::class, 1);

        $usage->increment('sms_segments', 199);        // 100%
        Notification::assertSentToTimes($this->admin, UsageThresholdReached::class, 2);

        $this->issue('Jane', '+12025550123');
        $this->assertSame('plan SMS allowance used up', SmsMessage::query()->sole()->status_reason);
    }

    public function test_overage_policy_keeps_sending(): void
    {
        $this->tenant->plan->update(['limits' => array_merge($this->tenant->plan->limits, ['sms_policy' => 'overage'])]);
        $this->actingAsTenant($this->tenant->fresh('plan'));
        SmsSetting::create(['provider' => 'log']);
        app(Usage::class)->increment('sms_segments', 1000);

        $this->issue('Jane', '+12025550123');
        $this->assertSame(SmsMessage::DELIVERED, SmsMessage::query()->sole()->status);
    }

    public function test_usage_counts_tickets_and_page_and_export(): void
    {
        $this->issue('A');
        $this->issue('B');
        $this->assertSame(2, app(Usage::class)->value('tickets'));

        $this->actingAs($this->admin);
        $this->tenantContext()->clear();
        $this->get('/admin/usage')->assertOk()->assertSee('Starter')->assertSee('Tickets this month');
        $csv = $this->get('/admin/usage/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('tickets,2', $csv);
    }

    public function test_platform_console_creates_tenant_changes_plan_and_suspends(): void
    {
        Notification::fake();
        $root = User::factory()->create();
        $root->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($root);
        $this->tenantContext()->clear();
        $pro = Plan::query()->where('code', 'pro')->sole();

        $this->get('/platform')->assertOk()->assertSee('Acme');

        Livewire::test(TenantsConsole::class)->set('creating', true)
            ->set('name', 'Bolt Bank')->set('planId', $pro->id)->set('adminName', 'Bo')->set('adminEmail', 'bo@bolt.test')
            ->call('create')->assertHasNoErrors()->assertSee('Bolt Bank created');

        $bolt = Tenant::query()->where('name', 'Bolt Bank')->sole();
        $bo = User::query()->where('email', 'bo@bolt.test')->sole();
        $this->assertSame($bolt->id, $bo->tenant_id);
        $this->assertTrue($this->inTenant($bolt, fn () => $bo->fresh()->hasRole(Roles::COMPANY_ADMIN)));
        Notification::assertSentTo($bo, ResetPassword::class);

        Livewire::test(TenantsConsole::class)->call('changePlan', $this->tenant->id, $pro->id);
        $this->assertSame($pro->id, $this->tenant->fresh()->plan_id);

        Livewire::test(TenantsConsole::class)->call('setStatus', $this->tenant->id, false);
        $this->assertFalse($this->tenant->fresh()->isActive());
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'platform.tenant_suspended')->exists());
    }
}
