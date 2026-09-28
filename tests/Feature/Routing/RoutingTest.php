<?php

namespace Tests\Feature\Routing;

use App\Domain\Organization\EmployeeShift;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\Exceptions\UnroutableServiceException;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\QueuePositions;
use App\Domain\Queue\TicketStatus;
use App\Domain\Routing\DepartmentResolver;
use App\Domain\Routing\Models\RoutingRule;
use App\Domain\Routing\Models\ServiceTimeStat;
use App\Domain\Routing\ServiceTimes;
use App\Domain\Routing\WaitEstimator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        [$a] = $this->twoTenants();
        $this->buildQueue($a, 'America/New_York');
    }

    public function test_default_department_when_no_rule_matches(): void
    {
        $decision = app(DepartmentResolver::class)->resolve($this->location, $this->service, CustomerType::WalkIn);

        $this->assertSame($this->dept->id, $decision->department->id);
        $this->assertNull($decision->rule);
    }

    public function test_time_based_rule_in_location_time_zone(): void
    {
        $general = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'G']);
        RoutingRule::create([
            'location_id' => $this->location->id, 'service_id' => $this->service->id,
            'weekdays' => [5], 'starts_at' => '16:00', 'ends_at' => '23:59', 'department_id' => $general->id, 'priority' => 3,
        ]);
        $resolver = app(DepartmentResolver::class);

        // Friday 2026-10-02 16:30 New York
        $fridayLate = CarbonImmutable::parse('2026-10-02 16:30', 'America/New_York');
        $decision = $resolver->resolve($this->location, $this->service, CustomerType::WalkIn, $fridayLate);
        $this->assertSame($general->id, $decision->department->id);
        $this->assertSame(3, $decision->priority);

        $this->assertSame($this->dept->id, $resolver->resolve($this->location, $this->service, CustomerType::WalkIn, $fridayLate->setTime(15, 0))->department->id);
        $this->assertSame($this->dept->id, $resolver->resolve($this->location, $this->service, CustomerType::WalkIn, $fridayLate->addDay())->department->id);
        // 20:30 UTC is 16:30 in New York.
        $this->assertSame($general->id, $resolver->resolve($this->location, $this->service, CustomerType::WalkIn, CarbonImmutable::parse('2026-10-02 20:30', 'UTC'))->department->id);
    }

    public function test_customer_type_rule_order_and_inactive_rules(): void
    {
        $appt = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'P']);
        $other = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'O']);
        RoutingRule::create(['location_id' => $this->location->id, 'sort_order' => 1, 'customer_type' => 'appointment', 'department_id' => $appt->id]);
        RoutingRule::create(['location_id' => $this->location->id, 'sort_order' => 2, 'customer_type' => 'appointment', 'department_id' => $other->id]);
        RoutingRule::create(['location_id' => $this->location->id, 'sort_order' => 0, 'department_id' => $other->id, 'is_active' => false]);
        $resolver = app(DepartmentResolver::class);

        $this->assertSame($appt->id, $resolver->resolve($this->location, $this->service, CustomerType::Appointment)->department->id);
        $this->assertSame($this->dept->id, $resolver->resolve($this->location, $this->service, CustomerType::WalkIn)->department->id);
    }

    public function test_service_not_offered_is_unroutable(): void
    {
        $other = Service::factory()->create();

        $this->assertNull(app(DepartmentResolver::class)->resolve($this->location, $other, CustomerType::WalkIn));
    }

    public function test_self_service_refuses_service_without_skilled_staff_but_receptionist_may_issue(): void
    {
        try {
            $this->issue('Kiosk Kim', overrides: ['channel' => CheckinChannel::Kiosk]);
            $this->fail('Expected UnroutableServiceException');
        } catch (UnroutableServiceException) {
        }

        $ticket = $this->issue('Desk Dan', overrides: ['channel' => CheckinChannel::Receptionist]);
        $this->assertSame(TicketStatus::Waiting, $ticket->status);

        $this->onShiftEmployee(start: false); // configured, even if offline
        $this->assertSame('Waiting', $this->issue('Kiosk Kim', overrides: ['channel' => CheckinChannel::Kiosk])->status->label());
    }

    public function test_wait_estimate_matches_spec_example_and_range(): void
    {
        // 4 ahead, 2 eligible staff working, 8 min average → ~16 min ("15–20")
        $this->onShiftEmployee('A');
        $this->onShiftEmployee('B');
        foreach (range(1, 4) as $i) {
            $this->issue("Ahead $i");
        }
        $mine = $this->issue('Me');

        $estimate = app(QueuePositions::class)->estimate($mine);
        $this->assertSame(4, $estimate['ahead']);
        $this->assertSame(5, $estimate['position']);
        $this->assertSame(2, $estimate['staff']);
        $this->assertSame(16, $estimate['minutes']);
        $this->assertSame([15, 20], app(WaitEstimator::class)->range(16));
        $this->assertSame('About 15–20 min', $estimate['label']);
        $this->assertSame(16, $mine->fresh()->estimated_wait_minutes);
    }

    public function test_no_working_staff_means_unavailable_estimate(): void
    {
        $employee = $this->onShiftEmployee();
        app(EmployeeShift::class)->setStatus($employee, EmployeeStatus::OnBreak, $this->location);

        $estimate = app(QueuePositions::class)->estimate($this->issue());

        $this->assertNull($estimate['minutes']);
        $this->assertSame('Wait time unavailable', $estimate['label']);
    }

    public function test_rolling_average_used_only_with_enough_samples(): void
    {
        $times = app(ServiceTimes::class);
        $this->assertSame(8.0, $times->averageMinutes($this->location, $this->service));

        // 20 completed tickets of 5 minutes each.
        foreach (range(1, ServiceTimes::MIN_SAMPLES) as $i) {
            $t = $this->issue("C$i");
            $t->forceFill(['status' => TicketStatus::Completed, 'service_seconds' => 300, 'completed_at' => now()])->save();
        }
        $times->refresh();

        $stat = ServiceTimeStat::query()->sole();
        $this->assertSame(20, $stat->sample_count);
        $this->assertSame(5.0, $times->averageMinutes($this->location, $this->service));

        // Old tickets fall out of the window.
        Ticket::query()->update(['completed_at' => now()->subDays(ServiceTimes::WINDOW_DAYS + 1)]);
        ServiceTimeStat::query()->delete();
        $times->refresh();
        $this->assertSame(8.0, $times->averageMinutes($this->location, $this->service));
    }
}
