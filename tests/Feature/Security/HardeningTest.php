<?php

namespace Tests\Feature\Security;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Analytics\Kpis;
use App\Domain\Analytics\ReportFilter;
use App\Domain\Analytics\StatsRollup;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Actor;
use App\Domain\Queue\Models\Customer;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\Models\TicketEvent;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Retention;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
    }

    public function test_retention_anonymizes_old_personal_data_but_keeps_facts(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['retention_days' => 90])])->save();
        $this->actingAsTenant($this->tenant->fresh());
        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00', 'UTC'));
        $maria = $this->onShiftEmployee();
        $old = $this->issue('Old Customer', '+12025550100');
        app(TicketStateMachine::class)->callNext($maria, $this->location, Actor::system());
        app(TicketStateMachine::class)->start($old, Actor::system());
        $this->travel(5)->minutes();
        app(TicketStateMachine::class)->complete($old, Actor::system());
        app(StatsRollup::class)->rollupDay('2026-01-05');

        $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
        $recent = $this->issue('New Customer', '+12025550199');

        $counts = app(Retention::class)->run();

        $old->refresh();
        $this->assertSame(Retention::PLACEHOLDER, $old->customer_name);
        $this->assertNull($old->customer_phone);
        $this->assertSame(300, $old->service_seconds, 'timings kept');
        $this->assertSame('New Customer', $recent->fresh()->customer_name);
        $this->assertNotNull(Customer::query()->find($old->customer_id)->anonymized_at);
        $this->assertSame(1, $counts['tickets']);

        $served = app(Kpis::class)->summary(new ReportFilter(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-31'), [$this->location->id]))['served'];
        $this->assertSame(1, $served, 'reports unaffected');
        $this->assertSame([], array_filter(app(Retention::class)->run()), 'idempotent');
    }

    public function test_security_headers_on_web_responses(): void
    {
        $this->tenantContext()->clear();

        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_public_check_in_is_rate_limited(): void
    {
        $this->tenantContext()->clear();
        $url = '/c/'.$this->location->checkin_public_id;

        for ($i = 0; $i < 60; $i++) {
            $this->get($url);
        }

        $this->get($url)->assertStatus(429);
    }

    public function test_every_tenant_owned_table_is_isolated_for_all_models(): void
    {
        // Seed one of each queue record in tenant A, then read everything as tenant B.
        $this->issue('Secret', '+12025550100');
        $b = Tenant::query()->where('id', '!=', $this->tenant->id)->first();

        $leaks = $this->inTenant($b, function () use ($b) {
            $found = [];
            foreach ([Ticket::class, Customer::class, TicketEvent::class, Location::class,
                Department::class, Service::class,
                Employee::class, AuditLog::class] as $model) {
                // Any row visible to tenant B that belongs to someone else is a leak.
                if ($model::query()->where((new $model)->qualifyColumn('tenant_id'), '!=', $b->id)->exists()) {
                    $found[] = $model;
                }
            }

            return $found;
        });

        $this->assertSame([], $leaks);
    }
}
