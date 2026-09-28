<?php

namespace Tests\Feature\Analytics;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Access\Roles;
use App\Domain\Analytics\Kpis;
use App\Domain\Analytics\Models\DailyStat;
use App\Domain\Analytics\ReportFilter;
use App\Domain\Analytics\StatsRollup;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Actor;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
        $this->admin = $this->userIn($this->tenant);
        $this->admin->assignRole(Roles::COMPANY_ADMIN);
        $this->freezeSecond();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC'));
    }

    /** Check in, wait, (optionally hold), serve for the given minutes. */
    private function served(int $waitMin, int $serviceMin, int $holdMin = 0, ?Department $dept = null): Ticket
    {
        $maria = Employee::query()->first() ?? $this->onShiftEmployee('Maria');
        $q = app(TicketStateMachine::class);
        $t = $this->issue('C', overrides: ['department' => $dept]);
        if ($holdMin) {
            $q->hold($t, Actor::system());
            $this->travel($holdMin)->minutes();
            $q->release($t, Actor::system());
        }
        $this->travel($waitMin)->minutes();
        $q->call($t, $maria->fresh(), Actor::system());
        $q->start($t, Actor::system());
        $this->travel($serviceMin)->minutes();
        $q->complete($t, Actor::system());

        return $t->fresh();
    }

    private function filter(string $from = '2026-10-01', string $to = '2026-10-31', ?int $location = null, ?int $department = null): ReportFilter
    {
        return ReportFilter::for($this->admin, $from, $to, $location, $department);
    }

    public function test_kpi_definitions_wait_excludes_hold(): void
    {
        $this->served(waitMin: 13, serviceMin: 6, holdMin: 5); // waited 10+3? see below
        $this->served(waitMin: 7, serviceMin: 4);
        $t = $this->issue('Walked away');
        app(TicketStateMachine::class)->cancelByCustomer($t);
        $n = $this->issue('No show');
        app(TicketStateMachine::class)->noShow($n, Actor::system());

        $s = app(Kpis::class)->summary($this->filter());

        $this->assertSame(4, $s['tickets']);
        $this->assertSame(2, $s['served']);
        $this->assertSame(10.0, $s['avg_wait_min']);     // (13 + 7) / 2, hold excluded
        $this->assertSame(5.0, $s['avg_service_min']);   // (6 + 4) / 2
        $this->assertSame(33.3, $s['no_show_rate']);     // 1 / (2 + 1)
        $this->assertSame(25.0, $s['abandon_rate']);     // 1 / 4
        $this->assertSame(0, $s['appointments']);
    }

    public function test_rollup_matches_live_and_is_idempotent(): void
    {
        $this->served(10, 5);
        $this->served(20, 5);
        $live = app(Kpis::class)->summary($this->filter());

        // A week later the day is history, served from daily_stats.
        app(StatsRollup::class)->rollupDay('2026-10-05');
        app(StatsRollup::class)->rollupDay('2026-10-05');
        $this->assertSame(1, DailyStat::query()->count());
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'UTC'));
        Ticket::query()->delete(); // prove history no longer reads tickets

        $history = app(Kpis::class)->summary($this->filter());
        $this->assertSame($live['served'], $history['served']);
        $this->assertSame($live['avg_wait_min'], $history['avg_wait_min']);
    }

    public function test_filters_combine_and_location_scope_is_enforced(): void
    {
        $loans = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'L']);
        $this->served(5, 5);
        $this->served(5, 5, dept: $loans);

        $this->assertSame(1, app(Kpis::class)->summary($this->filter(department: $loans->id))['served']);

        $other = Location::factory()->create();
        $manager = $this->userIn($this->tenant);
        $manager->assignRole(Roles::LOCATION_MANAGER);
        $manager->locations()->attach($other);
        $scoped = ReportFilter::for($manager, '2026-10-01', '2026-10-31', $this->location->id);
        $this->assertSame([], $scoped->locationIds);
        $this->assertSame(0, app(Kpis::class)->summary($scoped)['served']);
    }

    public function test_peak_hours_use_location_local_time(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['timezone' => 'America/New_York'])])->save();
        $this->actingAsTenant($this->tenant->fresh());
        $this->location->update(['timezone' => 'America/New_York']);
        $this->issue('A'); // 10:00 UTC = 06:00 New York, Monday

        $grid = app(Kpis::class)->peakHours($this->filter());
        $this->assertSame(1, $grid[1][6]);
    }

    public function test_breakdowns_and_trend(): void
    {
        $this->served(5, 5);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00', 'UTC'));
        $this->served(5, 5);

        $trend = app(Kpis::class)->trend($this->filter(), 'day');
        $this->assertSame(['2026-10-05', '2026-10-06'], array_column($trend, 'period'));
        $byEmployee = app(Kpis::class)->breakdown($this->filter(), 'employee');
        $this->assertSame(2, $byEmployee[0]['served']);
        $this->assertSame(1, count(app(Kpis::class)->trend($this->filter(), 'month')));
    }

    public function test_reports_page_and_csv_export_is_audited(): void
    {
        $this->served(5, 5);
        $this->actingAs($this->admin);
        $this->tenantContext()->clear();

        $this->get('/admin/reports?from=2026-10-01&to=2026-10-31')->assertOk()->assertSee('data-testid="kpi-served"', false);
        $this->get('/admin/overview')->assertOk()->assertSee('Main');

        $csv = $this->get('/admin/reports/export?type=employee&from=2026-10-01&to=2026-10-31')->assertOk();
        $this->assertStringContainsString('employee,check_ins,served', $csv->streamedContent());
        $this->assertTrue($this->inTenant($this->tenant, fn () => AuditLog::query()->where('action', 'report.exported')->exists()));
    }

    public function test_csv_neutralizes_formula_injection(): void
    {
        $employee = $this->onShiftEmployee('=HYPERLINK("evil")');
        $this->served(5, 5);
        $this->actingAs($this->admin);
        $this->tenantContext()->clear();

        $csv = $this->get('/admin/reports/export?type=employee&from=2026-10-01&to=2026-10-31')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_twelve_months_report_is_fast(): void
    {
        // ~12 months of rollups: 365 days × 3 departments × 2 services × 3 employees.
        $rows = [];
        $day = CarbonImmutable::parse('2025-10-01');
        for ($d = 0; $d < 365; $d++) {
            foreach ([1, 2, 3] as $dept) {
                foreach ([1, 2] as $svc) {
                    foreach ([1, 2, 3] as $emp) {
                        $rows[] = [
                            'tenant_id' => $this->tenant->id, 'local_date' => $day->addDays($d)->toDateString(),
                            'location_id' => $this->location->id, 'department_id' => $dept, 'service_id' => $svc,
                            'employee_id' => $emp, 'customer_type' => 'walk_in', 'tickets' => 10, 'completed' => 9,
                            'no_shows' => 1, 'wait_sum' => 5400, 'wait_count' => 9, 'service_sum' => 2700, 'service_count' => 9,
                            'checkins_by_hour' => json_encode(array_fill(0, 24, 0)), 'created_at' => now(), 'updated_at' => now(),
                        ];
                    }
                }
            }
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            \DB::table('daily_stats')->insert($chunk);
        }

        $start = microtime(true);
        $s = app(Kpis::class)->summary($this->filter('2025-10-01', '2026-10-05'));
        app(Kpis::class)->trend($this->filter('2025-10-01', '2026-10-05'), 'week');
        app(Kpis::class)->peakHours($this->filter('2025-10-01', '2026-10-05'));
        $elapsed = microtime(true) - $start;

        $this->assertSame(10.0, $s['avg_wait_min']);
        $this->assertLessThan(5.0, $elapsed, "12-month report took {$elapsed}s");
    }
}
