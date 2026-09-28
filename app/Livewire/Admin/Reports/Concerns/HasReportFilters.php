<?php

namespace App\Livewire\Admin\Reports\Concerns;

use App\Domain\Analytics\ReportFilter;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;

/** Shared report filters (analytics-reporting "Report filters"). */
trait HasReportFilters
{
    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?int $location = null;

    #[Url]
    public ?int $department = null;

    #[Url]
    public ?int $service = null;

    #[Url]
    public ?int $employee = null;

    public function bootHasReportFilters(): void
    {
        $today = CarbonImmutable::now(app(TenantContext::class)->require()->settings()->timezone());
        $this->to ??= $today->toDateString();
        $this->from ??= $today->subDays(29)->toDateString();
    }

    /** Quick ranges: today, 7d, 30d, month, quarter, 12m. */
    public function range(string $preset): void
    {
        $today = CarbonImmutable::now(app(TenantContext::class)->require()->settings()->timezone());
        [$this->from, $this->to] = match ($preset) {
            'today' => [$today->toDateString(), $today->toDateString()],
            '7d' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            'month' => [$today->startOfMonth()->toDateString(), $today->toDateString()],
            'quarter' => [$today->startOfQuarter()->toDateString(), $today->toDateString()],
            '12m' => [$today->subMonths(12)->addDay()->toDateString(), $today->toDateString()],
            default => [$today->subDays(29)->toDateString(), $today->toDateString()],
        };
    }

    protected function filter(): ReportFilter
    {
        $this->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date'],
            'location' => ['nullable', 'integer'], 'department' => ['nullable', 'integer'],
            'service' => ['nullable', 'integer'], 'employee' => ['nullable', 'integer'],
        ]);

        return ReportFilter::for(auth()->user(), (string) $this->from, (string) $this->to, $this->location, $this->department, $this->service, $this->employee);
    }

    /** @return array<string, int|string|null> */
    protected function filterQuery(): array
    {
        return array_filter([
            'from' => $this->from, 'to' => $this->to, 'location' => $this->location,
            'department' => $this->department, 'service' => $this->service, 'employee' => $this->employee,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
