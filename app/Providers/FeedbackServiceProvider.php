<?php

namespace App\Providers;

use App\Domain\Feedback\FeedbackRequests;
use App\Domain\Feedback\Models\FeedbackRequest;
use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\PublicIdentifierResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FeedbackServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(QueueChanged::class, FeedbackRequests::class);

        // /f/{feedback}: single-use feedback link by token.
        $this->app->make(PublicIdentifierResolver::class)->register('feedback', function (string $token) {
            $request = FeedbackRequest::withoutTenantScope()->where('token', $token)->first();
            $tenant = $request ? Tenant::query()->with('plan')->find($request->tenant_id) : null;

            return $tenant ? [$tenant, $request] : null;
        });
    }
}
