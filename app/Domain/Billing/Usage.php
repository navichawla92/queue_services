<?php

namespace App\Domain\Billing;

use App\Domain\Access\Models\Device;
use App\Domain\Access\Roles;
use App\Domain\Billing\Models\UsageCounter;
use App\Domain\Billing\Notifications\UsageThresholdReached;
use App\Domain\Organization\Models\Location;
use App\Domain\Signage\Models\SignageItem;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Usage metering (saas-plans-usage "Usage metering"): per-period counters
 * for flow metrics, live counts for resources, and 80% / 100% warnings.
 */
class Usage
{
    public const METERED = ['sms_segments' => 'sms_monthly', 'tickets' => null, 'appointments' => null];

    public function __construct(private readonly TenantContext $tenants) {}

    public function period(): string
    {
        return now($this->tenants->require()->settings()->timezone())->format('Y-m');
    }

    /** Atomically add to a metered counter; warns admins when crossing 80% / 100%. */
    public function increment(string $metric, int $by = 1): int
    {
        $tenant = $this->tenants->require();
        $period = $this->period();

        DB::table('usage_counters')->insertOrIgnore([
            'tenant_id' => $tenant->id, 'metric' => $metric, 'period' => $period, 'value' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        UsageCounter::query()->where(['metric' => $metric, 'period' => $period])->increment('value', $by);

        $counter = UsageCounter::query()->where(['metric' => $metric, 'period' => $period])->first();
        $limitKey = self::METERED[$metric] ?? null;
        $limit = $limitKey ? $tenant->plan?->limit($limitKey) : null;
        if ($counter && $limit) {
            $this->warnIfCrossed($tenant, $counter, $limitKey, (int) $limit);
        }

        return $counter === null ? 0 : $counter->value;
    }

    public function value(string $metric, ?string $period = null): int
    {
        return (int) UsageCounter::query()->where(['metric' => $metric, 'period' => $period ?? $this->period()])->value('value');
    }

    /** @return array<string, int> current resource counts (gauges) */
    public function resources(): array
    {
        return [
            'locations' => Location::query()->active()->count(),
            'staff_users' => User::query()->where('tenant_id', $this->tenants->id())->where('is_active', true)->count(),
            'displays' => Device::query()->whereNull('revoked_at')->count(),
            'media_storage_mb' => (int) ceil(SignageItem::query()->sum('media_size') / 1048576),
        ];
    }

    /**
     * Rows for the usage page / export: metric, used, limit, percent.
     *
     * @return list<array{metric: string, label: string, used: int, limit: int|null, percent: float|null}>
     */
    public function report(?string $period = null): array
    {
        $plan = $this->tenants->require()->plan;
        $rows = [];
        foreach ($this->resources() as $key => $used) {
            $rows[] = $this->row($key, PlanCatalog::LIMITS[$key], $used, $plan?->limit($key));
        }
        $rows[] = $this->row('sms_segments', __('SMS segments this month'), $this->value('sms_segments', $period), $plan?->limit('sms_monthly'));
        $rows[] = $this->row('tickets', __('Tickets this month'), $this->value('tickets', $period), null);
        $rows[] = $this->row('appointments', __('Appointments booked this month'), $this->value('appointments', $period), null);

        return $rows;
    }

    /** @return list<array{metric: string, label: string, used: int, limit: int|null, percent: float|null}> rows at ≥ 80% */
    public function warnings(): array
    {
        return array_values(array_filter($this->report(), fn ($r) => $r['percent'] !== null && $r['percent'] >= 80));
    }

    /** @return array{metric: string, label: string, used: int, limit: int|null, percent: float|null} */
    private function row(string $metric, string $label, int $used, ?int $limit): array
    {
        return [
            'metric' => $metric, 'label' => $label, 'used' => $used, 'limit' => $limit,
            'percent' => $limit ? round($used / $limit * 100, 1) : null,
        ];
    }

    private function warnIfCrossed(Tenant $tenant, UsageCounter $counter, string $limitKey, int $limit): void
    {
        foreach ([100 => 'warned_100', 80 => 'warned_80'] as $threshold => $flag) {
            if ($counter->value * 100 >= $limit * $threshold && ! $counter->{$flag}) {
                // Claim the flag atomically so only one request sends the email.
                $claimed = UsageCounter::query()->whereKey($counter->id)->where($flag, false)->update([$flag => true]);
                if ($claimed) {
                    $admins = User::query()->where('tenant_id', $tenant->id)->where('is_active', true)
                        ->whereHas('roles', fn ($q) => $q->where('name', Roles::COMPANY_ADMIN))->get();
                    Notification::send($admins, new UsageThresholdReached($limitKey, $threshold, $counter->value, $limit));
                }
                break;
            }
        }
    }
}
