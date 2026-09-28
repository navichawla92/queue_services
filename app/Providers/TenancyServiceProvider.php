<?php

namespace App\Providers;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\PublicIdentifierResolver;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Tenant contexts saved while a queued job runs, so a synchronously
     * executed job restores the caller's tenant afterwards.
     *
     * @var list<Tenant|null>
     */
    private array $jobContextStack = [];

    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(PublicIdentifierResolver::class);
    }

    public function boot(): void
    {
        $this->propagateTenantToQueuedJobs();
    }

    /**
     * Every queued job carries the dispatching tenant's id and runs inside
     * that tenant's context (design.md Decision 2d).
     */
    private function propagateTenantToQueuedJobs(): void
    {
        Queue::createPayloadUsing(fn () => ['tenant_id' => app(TenantContext::class)->id()]);

        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $context = app(TenantContext::class);
            $this->jobContextStack[] = $context->get();

            $tenantId = $event->job->payload()['tenant_id'] ?? null;
            $context->set($tenantId ? Tenant::query()->with('plan')->find($tenantId) : null);
        });

        $restore = function () {
            if ($this->jobContextStack !== []) {
                app(TenantContext::class)->set(array_pop($this->jobContextStack));
            }
        };

        Event::listen(JobProcessed::class, $restore);
        Event::listen(JobExceptionOccurred::class, $restore);
    }
}
