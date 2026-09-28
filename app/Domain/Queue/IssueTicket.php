<?php

namespace App\Domain\Queue;

use App\Domain\Billing\Usage;
use App\Domain\Queue\Exceptions\DuplicateCheckinException;
use App\Domain\Queue\Exceptions\UnroutableServiceException;
use App\Domain\Queue\Models\Customer;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\Models\TicketEvent;
use App\Domain\Routing\DepartmentResolver;
use App\Domain\Routing\Routability;
use App\Domain\Routing\RoutingDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Creates a Waiting ticket (customer-check-in "Ticket issuance"): duplicate
 * check, routing, returning-customer match, daily number, initial estimate.
 * Runs under the location's QueueLock.
 */
class IssueTicket
{
    public function __construct(
        private readonly QueueLock $lock,
        private readonly DepartmentResolver $resolver,
        private readonly Routability $routability,
        private readonly TicketNumberer $numberer,
        private readonly QueuePositions $positions,
    ) {}

    /**
     * @throws DuplicateCheckinException
     * @throws UnroutableServiceException
     */
    public function __invoke(CheckinRequest $request, Actor $actor): Ticket
    {
        $location = $request->location;

        return $this->lock->run($location, function () use ($request, $actor, $location) {
            if ($request->phone !== null) {
                $existing = Ticket::query()->where('location_id', $location->id)
                    ->where('customer_phone', $request->phone)->active()->first();
                if ($existing) {
                    throw new DuplicateCheckinException($existing);
                }
            }

            $decision = $this->route($request);
            $now = CarbonImmutable::now();
            $localDate = $now->setTimezone($location->effectiveTimezone())->toDateString();
            $format = (string) $location->tenant->settings()->get('ticket_number_format');
            $number = $this->numberer->next($decision->department, $localDate, $format);

            $customer = $this->matchCustomer($request);

            $ticket = Ticket::create([
                'location_id' => $location->id,
                'department_id' => $decision->department->id,
                'service_id' => $request->service->id,
                'customer_id' => $customer->id,
                'appointment_id' => $request->appointmentId,
                'number' => $number['number'],
                'sequence' => $number['sequence'],
                'local_date' => $localDate,
                'channel' => $request->channel,
                'customer_type' => $request->customerType,
                'status' => TicketStatus::Waiting,
                'priority' => $decision->priority + $request->extraPriority,
                'public_token' => Str::random(40),
                'customer_name' => $request->name,
                'customer_phone' => $request->phone,
                'sms_consent' => $request->smsConsent && $request->phone !== null,
                'assigned_employee_id' => $request->assignedEmployeeId,
                'checked_in_at' => $now,
                'queued_at' => $now,
            ]);

            $estimate = $this->positions->estimate($ticket);
            $ticket->forceFill(['estimated_wait_minutes' => $estimate['minutes'], 'initial_ahead' => $estimate['ahead']])->save();

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'type' => 'created',
                'to_status' => TicketStatus::Waiting->value,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'to_department_id' => $decision->department->id,
                'meta' => ['channel' => $request->channel->value, 'rule_id' => $decision->rule?->id],
                'created_at' => $now,
            ]);
            $this->lock->changed($location->id, 'created', $ticket->id);
            app(Usage::class)->increment('tickets');

            return $ticket;
        });
    }

    private function route(CheckinRequest $request): RoutingDecision
    {
        if ($request->department !== null) {
            if ($request->department->location_id !== $request->location->id || ! $request->department->is_active) {
                throw new UnroutableServiceException;
            }

            return new RoutingDecision($request->department);
        }

        $decision = $request->channel->isSelfService()
            ? $this->routability->check($request->location, $request->service, $request->customerType)
            : $this->resolver->resolve($request->location, $request->service, $request->customerType);

        return $decision ?? throw new UnroutableServiceException;
    }

    private function matchCustomer(CheckinRequest $request): Customer
    {
        $customer = $request->phone !== null
            ? Customer::query()->where('phone', $request->phone)->first()
            : null;

        $customer ??= new Customer(['phone' => $request->phone]);
        $customer->name = $request->name;
        $customer->visit_count++;
        $customer->last_visit_at = now();
        $customer->save();

        return $customer;
    }
}
