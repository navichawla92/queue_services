<?php

namespace App\Domain\Display;

use App\Domain\Access\Models\Device;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\QueueOrdering;
use App\Domain\Queue\QueueSnapshot;
use App\Domain\Queue\TicketStatus;
use Illuminate\Support\Facades\Storage;

/**
 * Everything a lobby display renders, privacy-filtered (lobby-display
 * spec): ticket numbers by default; "First L." only when the tenant opts
 * in; never full names or phone numbers.
 */
class DisplaySnapshot
{
    public function __construct(
        private readonly QueueSnapshot $queue,
        private readonly QueueOrdering $ordering,
    ) {}

    /** @return array<string, mixed> */
    public function for(Device $device): array
    {
        $location = $device->location;
        $tenant = $location->tenant;
        $config = DisplayConfig::for($device);
        $showNames = (bool) $tenant->settings()->get('show_customer_names_on_display', false);
        $departments = $config['department_ids'];

        $base = Ticket::query()->with(['desk', 'servingEmployee', 'department'])
            ->where('location_id', $location->id)
            ->when($departments !== [], fn ($q) => $q->whereIn('department_id', $departments));

        $serving = (clone $base)
            ->whereIn('status', [TicketStatus::Called->value, TicketStatus::InService->value])
            ->orderByDesc('called_at')->get();

        $waitingQuery = (clone $base)->where('status', TicketStatus::Waiting->value);
        $waitingTotal = (clone $waitingQuery)->count();
        $waiting = $this->ordering->ordered($waitingQuery)->limit($config['waiting_rows'])->get();

        $avgWait = [];
        if ($config['show_avg_wait']) {
            $avgWait = (clone $base)->whereNotNull('wait_seconds')
                ->whereDate('local_date', now($location->effectiveTimezone())->toDateString())
                ->groupBy('department_id')
                ->selectRaw('department_id, AVG(wait_seconds) as avg_wait')
                ->toBase()->get()
                ->map(fn ($r) => ['department_id' => (int) $r->department_id, 'minutes' => (int) round($r->avg_wait / 60)])
                ->values()->all();
        }

        $name = fn (Ticket $t) => $showNames ? $t->shortName() : null;

        return [
            'version' => $this->queue->version($location),
            'server_time' => now()->toIso8601String(),
            'timezone' => $location->effectiveTimezone(),
            'config' => $config,
            'location' => ['name' => $location->name],
            'brand' => [
                'name' => $tenant->brandName(),
                'logo' => $tenant->logo_path ? Storage::disk(config('filesystems.media'))->url($tenant->logo_path) : null,
                'primary_color' => $tenant->primary_color,
                'accent_color' => $tenant->accent_color,
                'powered_by' => $tenant->isWhiteLabel() ? null : config('app.name'),
            ],
            'serving' => $serving->map(fn (Ticket $t) => [
                // Changes whenever the ticket is (re)called → drives the highlight.
                'call_key' => $t->id.':'.$t->recall_count.':'.$t->called_at?->getTimestamp(),
                'number' => $t->number,
                'name' => $name($t),
                'desk' => $t->desk?->label,
                'employee' => $config['show_employee_name'] ? $t->servingEmployee?->display_name : null,
                'status' => $t->status->value,
                'called_at' => $t->called_at?->toIso8601String(),
                'color' => $t->department->color,
            ])->values()->all(),
            'waiting' => $waiting->map(fn (Ticket $t) => [
                'number' => $t->number,
                'name' => $name($t),
                'department' => $t->department->name,
                'color' => $t->department->color,
            ])->values()->all(),
            'waiting_more' => max(0, $waitingTotal - $waiting->count()),
            'avg_wait' => $avgWait,
            'departments' => $location->departments()->active()->ordered()->get(['id', 'name', 'color'])
                ->when($departments !== [], fn ($c) => $c->whereIn('id', $departments))->values()->all(),
            'ticker' => app(TickerMessages::class)->for($location),
            'signage' => app(SignageFeed::class)->hash($device),
        ];
    }
}
