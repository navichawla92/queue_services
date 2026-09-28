<?php

namespace App\Domain\Analytics;

use App\Domain\Analytics\Models\DailyStat;
use App\Domain\Feedback\Models\FeedbackResponse;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Scheduling\AppointmentStatus;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * KPI computation with the definitions of analytics-reporting "KPI
 * definitions". History comes from daily_stats; the last two local days are
 * always aggregated live from tickets (late changes, time-zone edges).
 */
class Kpis
{
    public const DEFINITIONS = [
        'served' => 'Tickets completed.',
        'avg_wait' => 'From check-in to the first call, excluding time on hold (tickets that were called).',
        'avg_service' => 'From service start to completion (completed tickets).',
        'no_show_rate' => 'No-shows ÷ (completed + no-shows).',
        'abandon_rate' => '(Left the queue + closed unserved) ÷ all tickets.',
        'appt_share' => 'Share of tickets that were appointment arrivals.',
        'satisfaction' => 'Average 1–5 rating of feedback submitted for visits in the period.',
        'appt_no_show_rate' => 'Appointments marked no-show ÷ (appointments completed/served + no-shows).',
    ];

    public function __construct(
        private readonly StatsRollup $rollup,
        private readonly TenantContext $tenants,
    ) {}

    /** @return Collection<int, array<string, mixed>> rollup-shaped rows for the filter */
    public function rows(ReportFilter $f): Collection
    {
        if ($f->locationIds === []) {
            return collect();
        }

        $liveFrom = CarbonImmutable::now($this->tenants->require()->settings()->timezone())->subDay()->startOfDay();

        $history = collect();
        if ($f->from->lessThan($liveFrom)) {
            $historyTo = $f->to->lessThan($liveFrom) ? $f->to : $liveFrom->subDay();
            $history = DailyStat::query()
                ->whereIn('location_id', $f->locationIds)
                ->whereBetween('local_date', [$f->from->toDateString(), $historyTo->toDateString()])
                ->when($f->departmentId, fn ($q, $v) => $q->where('department_id', $v))
                ->when($f->serviceId, fn ($q, $v) => $q->where('service_id', $v))
                ->when($f->employeeId, fn ($q, $v) => $q->where('employee_id', $v))
                ->get()
                ->map(fn (DailyStat $s) => array_merge($s->getAttributes(), [
                    'local_date' => $s->local_date->format('Y-m-d'),
                    'checkins_by_hour' => $s->checkins_by_hour,
                ]));
        }

        $live = collect();
        if ($f->to->greaterThanOrEqualTo($liveFrom)) {
            $live = collect($this->rollup->aggregate(Ticket::query()
                ->whereIn('location_id', $f->locationIds)
                ->whereBetween('local_date', [$f->from->max($liveFrom)->toDateString(), $f->to->toDateString()])
                ->when($f->departmentId, fn ($q, $v) => $q->where('department_id', $v))
                ->when($f->serviceId, fn ($q, $v) => $q->where('service_id', $v))
                ->when($f->employeeId, fn ($q, $v) => $q->where('serving_employee_id', $v))));
        }

        return $history->concat($live)->values();
    }

    /** @return array<string, float|int|null> headline KPIs */
    public function summary(ReportFilter $f): array
    {
        $rows = $this->rows($f);
        $sum = fn (string $k) => (int) $rows->sum($k);
        $tickets = $sum('tickets');
        $completed = $sum('completed');
        $noShows = $sum('no_shows');
        $appointments = (int) $rows->where('customer_type', 'appointment')->sum('tickets');
        $feedback = $this->feedback($f);
        $apptNoShow = $this->appointmentNoShows($f);

        return [
            'tickets' => $tickets,
            'served' => $completed,
            'avg_wait_min' => $this->minutes($sum('wait_sum'), $sum('wait_count')),
            'avg_service_min' => $this->minutes($sum('service_sum'), $sum('service_count')),
            'no_show_rate' => $this->rate($noShows, $completed + $noShows),
            'abandon_rate' => $this->rate($sum('cancelled') + $sum('closed_unserved'), $tickets),
            'appointments' => $appointments,
            'walk_ins' => $tickets - $appointments,
            'appt_share' => $this->rate($appointments, $tickets),
            'satisfaction' => $feedback['avg'],
            'feedback_count' => $feedback['count'],
            'appt_no_show_rate' => $this->rate($apptNoShow['no_shows'], $apptNoShow['kept'] + $apptNoShow['no_shows']),
        ];
    }

    /**
     * Volume, wait and service per day / ISO week / month.
     *
     * @return list<array{period: string, tickets: int, served: int, avg_wait_min: float|null, avg_service_min: float|null}>
     */
    public function trend(ReportFilter $f, string $grain = 'day'): array
    {
        return $this->rows($f)
            ->groupBy(fn ($r) => match ($grain) {
                'week' => CarbonImmutable::parse($r['local_date'])->format('o-\WW'),
                'month' => substr($r['local_date'], 0, 7),
                default => $r['local_date'],
            })
            ->sortKeys()
            ->map(fn (Collection $g, string $period) => [
                'period' => $period,
                'tickets' => (int) $g->sum('tickets'),
                'served' => (int) $g->sum('completed'),
                'avg_wait_min' => $this->minutes((int) $g->sum('wait_sum'), (int) $g->sum('wait_count')),
                'avg_service_min' => $this->minutes((int) $g->sum('service_sum'), (int) $g->sum('service_count')),
            ])->values()->all();
    }

    /**
     * Breakdown by employee | department | service | location.
     *
     * @return list<array{id: int, tickets: int, served: int, avg_wait_min: float|null, avg_service_min: float|null, no_show_rate: float|null}>
     */
    public function breakdown(ReportFilter $f, string $by): array
    {
        $key = ['employee' => 'employee_id', 'department' => 'department_id', 'service' => 'service_id', 'location' => 'location_id'][$by];

        return $this->rows($f)
            ->when($by === 'employee', fn ($c) => $c->where('employee_id', '>', 0))
            ->groupBy($key)
            ->map(function (Collection $g, $id) {
                $completed = (int) $g->sum('completed');
                $noShows = (int) $g->sum('no_shows');

                return [
                    'id' => (int) $id,
                    'tickets' => (int) $g->sum('tickets'),
                    'served' => $completed,
                    'avg_wait_min' => $this->minutes((int) $g->sum('wait_sum'), (int) $g->sum('wait_count')),
                    'avg_service_min' => $this->minutes((int) $g->sum('service_sum'), (int) $g->sum('service_count')),
                    'no_show_rate' => $this->rate($noShows, $completed + $noShows),
                ];
            })
            ->sortByDesc('served')->values()->all();
    }

    /** @return array<int, array<int, int>> check-ins [dayOfWeek 0–6][hour 0–23] */
    public function peakHours(ReportFilter $f): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($this->rows($f) as $r) {
            $dow = CarbonImmutable::parse($r['local_date'])->dayOfWeek;
            foreach ($r['checkins_by_hour'] as $hour => $n) {
                $grid[$dow][$hour] += (int) $n;
            }
        }

        return $grid;
    }

    /** @return array{avg: float|null, count: int} */
    public function feedback(ReportFilter $f, ?string $groupBy = null): array
    {
        $q = FeedbackResponse::query()
            ->join('tickets', 'tickets.id', '=', 'feedback_responses.ticket_id')
            ->whereIn('feedback_responses.location_id', $f->locationIds ?: [0])
            ->whereBetween('tickets.local_date', [$f->from->toDateString(), $f->to->toDateString()])
            ->when($f->departmentId, fn ($q, $v) => $q->where('feedback_responses.department_id', $v))
            ->when($f->serviceId, fn ($q, $v) => $q->where('feedback_responses.service_id', $v))
            ->when($f->employeeId, fn ($q, $v) => $q->where('feedback_responses.employee_id', $v));

        $count = (clone $q)->count();

        return ['avg' => $count ? round((float) $q->avg('feedback_responses.rating'), 2) : null, 'count' => $count];
    }

    /** @return array<int, array{avg: float, count: int}> satisfaction per location/employee/department id */
    public function feedbackBy(ReportFilter $f, string $by): array
    {
        $col = 'feedback_responses.'.['employee' => 'employee_id', 'department' => 'department_id', 'service' => 'service_id', 'location' => 'location_id'][$by];

        return FeedbackResponse::query()
            ->join('tickets', 'tickets.id', '=', 'feedback_responses.ticket_id')
            ->whereIn('feedback_responses.location_id', $f->locationIds ?: [0])
            ->whereBetween('tickets.local_date', [$f->from->toDateString(), $f->to->toDateString()])
            ->whereNotNull($col)
            ->groupBy($col)
            ->selectRaw("{$col} as gid, AVG(feedback_responses.rating) as avg_rating, COUNT(*) as n")
            ->toBase()->get()
            ->mapWithKeys(fn ($r) => [(int) $r->gid => ['avg' => round((float) $r->avg_rating, 2), 'count' => (int) $r->n]])
            ->all();
    }

    /** @return array{no_shows: int, kept: int} appointments starting in the period (per location local dates) */
    private function appointmentNoShows(ReportFilter $f): array
    {
        $q = Appointment::query()->where(function ($q) use ($f) {
            foreach (Location::query()->whereIn('id', $f->locationIds ?: [0])->get() as $loc) {
                $tz = $loc->effectiveTimezone();
                $q->orWhere(fn ($q) => $q->where('location_id', $loc->id)->whereBetween('starts_at', [
                    CarbonImmutable::parse($f->from->toDateString(), $tz)->startOfDay()->utc(),
                    CarbonImmutable::parse($f->to->toDateString(), $tz)->endOfDay()->utc(),
                ]));
            }
        })
            ->when($f->serviceId, fn ($q, $v) => $q->where('service_id', $v))
            ->when($f->employeeId, fn ($q, $v) => $q->where('employee_id', $v));

        return [
            'no_shows' => (clone $q)->where('status', AppointmentStatus::NoShow->value)->count(),
            'kept' => (clone $q)->whereIn('status', [AppointmentStatus::Arrived->value, AppointmentStatus::InService->value, AppointmentStatus::Completed->value])->count(),
        ];
    }

    private function minutes(int $sum, int $count): ?float
    {
        return $count > 0 ? round($sum / $count / 60, 1) : null;
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
