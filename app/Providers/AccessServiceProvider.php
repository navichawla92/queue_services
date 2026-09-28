<?php

namespace App\Providers;

use App\Domain\Access\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Role;

/** Audit hooks for authentication and role changes. */
class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $this->audit()->log('auth.sign_in', $event->user, tenantId: $event->user->tenant_id,
                    actor: ['type' => 'user', 'id' => $event->user->id, 'name' => $event->user->name]);
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                $this->audit()->log('auth.sign_out', $event->user, tenantId: $event->user->tenant_id,
                    actor: ['type' => 'user', 'id' => $event->user->id, 'name' => $event->user->name]);
            }
        });

        Event::listen(RoleAttached::class, fn (RoleAttached $e) => $this->logRoleChange('user.role_attached', $e->model, $e->rolesOrIds));
        Event::listen(RoleDetached::class, fn (RoleDetached $e) => $this->logRoleChange('user.role_detached', $e->model, $e->rolesOrIds));
    }

    private function logRoleChange(string $action, mixed $model, mixed $rolesOrIds): void
    {
        if (! $model instanceof User) {
            return;
        }

        $ids = collect(is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds])
            ->map(fn ($r) => $r instanceof Role ? $r->id : $r)->all();
        $names = Role::query()->whereIn('id', $ids)->pluck('name')->all();

        $this->audit()->log($action, $model, after: ['roles' => $names], tenantId: $model->tenant_id);
    }

    private function audit(): AuditLogger
    {
        return $this->app->make(AuditLogger::class);
    }
}
