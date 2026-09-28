<?php

namespace Tests\Feature\Queue;

use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Actor;
use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Queue\Exceptions\DuplicateCheckinException;
use App\Domain\Queue\Exceptions\QueueActionException;
use App\Domain\Queue\Models\Customer;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\Models\TicketEvent;
use App\Domain\Queue\Notes;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private TicketStateMachine $queue;

    protected function setUp(): void
    {
        parent::setUp();
        // DATETIME columns store whole seconds; keep "now" on a second boundary
        // so travelled durations are exact.
        $this->freezeSecond();
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
        $this->queue = app(TicketStateMachine::class);
    }

    public function test_ticket_numbers_are_sequential_per_department_and_reset_daily(): void
    {
        $this->assertSame('A-001', $this->issue('One')->number);
        $this->assertSame('A-002', $this->issue('Two')->number);

        $b = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'B']);
        $this->assertSame('B-001', $this->issue('Three', overrides: ['department' => $b])->number);

        $this->travel(1)->days();
        $this->assertSame('A-001', $this->issue('Tomorrow')->number);
    }

    public function test_full_happy_path_records_timings_events_and_employee_status(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $ticket = $this->issue('John Doe');

        $this->travel(5)->minutes();
        $called = $this->queue->callNext($maria, $this->location, Actor::employee($maria));
        $this->assertTrue($called->is($ticket));
        $this->assertSame(TicketStatus::Called, $called->status);
        $this->assertSame($maria->current_desk_id, $called->desk_id);
        $this->assertSame(300, $called->wait_seconds);
        $this->assertSame(EmployeeStatus::Busy, $maria->fresh()->status);

        $this->queue->start($called, Actor::employee($maria));
        $this->travel(7)->minutes();
        $done = $this->queue->complete($called, Actor::employee($maria), 'Resolved');

        $this->assertSame(TicketStatus::Completed, $done->status);
        $this->assertSame(420, $done->service_seconds);
        $this->assertSame(EmployeeStatus::Available, $maria->fresh()->status);
        $this->assertSame(['created', 'called', 'started', 'completed'], TicketEvent::query()->where('ticket_id', $ticket->id)->orderBy('id')->pluck('type')->all());
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $ticket = $this->issue();

        $this->expectException(QueueActionException::class);
        $this->queue->complete($ticket, Actor::system());
    }

    public function test_call_next_order_priority_then_time_and_direct_assignment_first(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $sam = $this->onShiftEmployee('Sam');

        $first = $this->issue('First');
        $this->travel(1)->minutes();
        $vip = $this->issue('Vip', overrides: ['priority' => 10]);
        $this->travel(1)->minutes();
        $forSam = $this->issue('ForSam', overrides: ['assignedEmployeeId' => $sam->id]);

        $this->assertTrue($this->queue->callNext($maria, $this->location, Actor::system())->is($vip));
        $this->assertTrue($this->queue->callNext($sam, $this->location, Actor::system())->is($forSam));
        $this->completeServing($maria);
        $this->assertTrue($this->queue->callNext($maria, $this->location, Actor::system())->is($first));
    }

    public function test_call_next_skips_tickets_the_employee_is_not_skilled_for(): void
    {
        $loans = Service::factory()->create(['name' => 'Loans']);
        $loans->offerAt($this->location, $this->dept);
        $depositsOnly = $this->onShiftEmployee('Dee', [$this->service]);

        $this->issue('Loan customer', overrides: ['service' => $loans]);
        $this->travel(1)->minutes();
        $deposit = $this->issue('Deposit customer');

        $this->assertTrue($this->queue->callNext($depositsOnly, $this->location, Actor::system())->is($deposit));
        $this->completeServing($depositsOnly);
        $this->assertNull($this->queue->callNext($depositsOnly, $this->location, Actor::system()));
    }

    public function test_employee_must_be_on_shift_and_free_to_call(): void
    {
        $offline = $this->onShiftEmployee('Off', start: false);
        $this->issue('A');
        $this->issue('B');

        try {
            $this->queue->callNext($offline, $this->location, Actor::system());
            $this->fail('Offline employee must not call');
        } catch (QueueActionException) {
        }

        $maria = $this->onShiftEmployee('Maria');
        $this->queue->callNext($maria, $this->location, Actor::system());
        $this->expectException(QueueActionException::class);
        $this->queue->callNext($maria, $this->location, Actor::system());
    }

    public function test_hold_time_is_excluded_from_wait_and_held_tickets_are_skipped(): void
    {
        $maria = $this->onShiftEmployee();
        $held = $this->issue('Held');
        $this->travel(1)->minutes();
        $other = $this->issue('Other');

        $this->travel(2)->minutes();                         // held ticket waited 3 min
        $this->queue->hold($held, Actor::system(), 'getting ID');
        $this->assertTrue($this->queue->callNext($maria, $this->location, Actor::system())->is($other));
        $this->completeServing($maria);

        $this->travel(5)->minutes();                         // 5 min on hold
        $this->queue->release($held, Actor::system());
        $this->assertSame(300, $held->fresh()->total_hold_seconds);
        $this->travel(1)->minutes();                         // +1 min waiting

        $called = $this->queue->callNext($maria, $this->location, Actor::system());
        $this->assertTrue($called->is($held));
        $this->assertSame(240, $called->wait_seconds);        // 3 + 1 min, hold excluded
    }

    public function test_recall_and_requeue(): void
    {
        $maria = $this->onShiftEmployee();
        $ticket = $this->issue();
        $this->queue->callNext($maria, $this->location, Actor::system());

        $this->queue->recall($ticket, Actor::system());
        $this->assertSame(1, $ticket->fresh()->recall_count);

        $this->queue->requeue($ticket, Actor::system());
        $this->assertSame(TicketStatus::Waiting, $ticket->fresh()->status);
        $this->assertSame(EmployeeStatus::Available, $maria->fresh()->status);
    }

    public function test_transfer_to_department_keeps_check_in_time_and_adds_note(): void
    {
        $maria = $this->onShiftEmployee();
        $loans = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'L']);
        $ticket = $this->issue();
        $queuedAt = $ticket->queued_at->toDateTimeString();
        $this->queue->callNext($maria, $this->location, Actor::system());
        $this->queue->start($ticket, Actor::system());

        $this->travel(3)->minutes();
        $this->actingAs($maria->user);
        $this->queue->transfer($ticket, $loans, null, Actor::user($maria->user), 'needs loan officer');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Waiting, $ticket->status);
        $this->assertSame($loans->id, $ticket->department_id);
        $this->assertSame($queuedAt, $ticket->queued_at->toDateTimeString());
        $this->assertSame(1, $ticket->transfer_count);
        $this->assertSame(180, $ticket->service_seconds);
        $this->assertNull($ticket->serving_employee_id);
        $this->assertSame('needs loan officer', $ticket->notes()->sole()->body);
        $this->assertSame(EmployeeStatus::Available, $maria->fresh()->status);
    }

    public function test_transfer_to_back_of_queue_when_tenant_prefers(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['transfer_keeps_queue_position' => false])])->save();
        $ticket = $this->issue();
        $this->travel(10)->minutes();

        $this->queue->transfer($ticket, null, $this->onShiftEmployee('Sam'), Actor::system());

        $this->assertTrue($ticket->fresh()->queued_at->equalTo(now()->startOfSecond()));
        $this->assertNotNull($ticket->fresh()->assigned_employee_id);
    }

    public function test_no_show_cancel_and_end_of_day_close(): void
    {
        $maria = $this->onShiftEmployee();
        $a = $this->issue('A', '+12025550101');
        $b = $this->issue('B');
        $c = $this->issue('C');

        $this->queue->callNext($maria, $this->location, Actor::system());
        $this->queue->noShow($a, Actor::system());
        $this->assertSame(1, Customer::query()->where('phone', '+12025550101')->value('no_show_count'));

        $this->queue->cancelByCustomer($b);
        $this->queue->hold($c, Actor::system());
        $this->queue->closeUnserved($c);

        $this->assertSame(['no_show', 'cancelled', 'closed_unserved'], Ticket::query()->orderBy('id')->pluck('status')->map->value->all());
    }

    public function test_duplicate_check_in_returns_existing_ticket_and_returning_customer_is_linked(): void
    {
        $first = $this->issue('Jane', '+12025550123');

        try {
            $this->issue('Jane again', '+12025550123');
            $this->fail('Expected duplicate');
        } catch (DuplicateCheckinException $e) {
            $this->assertTrue($e->existing->is($first));
        }

        $this->queue->cancelByCustomer($first);
        $second = $this->issue('Jane', '+12025550123');

        $this->assertSame($first->customer_id, $second->customer_id);
        $this->assertSame(2, Customer::query()->find($second->customer_id)->visit_count);
    }

    public function test_queue_changes_are_announced_after_commit_with_increasing_version(): void
    {
        Event::fake([QueueChanged::class]);
        $maria = $this->onShiftEmployee();

        $ticket = $this->issue();
        $this->queue->callNext($maria, $this->location, Actor::system());

        $versions = [];
        Event::assertDispatched(QueueChanged::class, function (QueueChanged $e) use (&$versions, $ticket) {
            $versions[] = $e->version;

            return $e->locationId === $this->location->id && in_array($ticket->id, $e->ticketIds(), true);
        });
        $this->assertSame([1, 2], $versions);
    }

    public function test_failed_action_rolls_back_and_announces_nothing(): void
    {
        Event::fake([QueueChanged::class]);
        $ticket = $this->issue();
        Event::assertDispatchedTimes(QueueChanged::class, 1);

        try {
            $this->queue->start($ticket, Actor::system());
        } catch (QueueActionException) {
        }

        Event::assertDispatchedTimes(QueueChanged::class, 1);
        $this->assertSame(1, TicketEvent::query()->count());
    }

    public function test_internal_notes_on_ticket_and_customer(): void
    {
        $author = $this->userIn($this->tenant);
        $ticket = $this->issue('Jane', '+12025550199');

        app(Notes::class)->addToTicket($ticket, $author, 'Bring passport', alsoOnCustomer: true);

        $this->assertSame('Bring passport', $ticket->notes()->sole()->body);
        $this->assertSame(1, Customer::query()->find($ticket->customer_id)->notes()->count());
    }

    public function test_other_tenant_cannot_see_tickets(): void
    {
        $this->issue();
        [, $b] = [$this->tenant, Tenant::query()->where('id', '!=', $this->tenant->id)->first()];

        $this->assertSame(0, $this->inTenant($b, fn () => Ticket::query()->count()));
    }

    private function completeServing($employee): void
    {
        $ticket = Ticket::query()->where('serving_employee_id', $employee->id)->where('status', 'called')->sole();
        $this->queue->start($ticket, Actor::system());
        $this->queue->complete($ticket, Actor::system());
    }
}
