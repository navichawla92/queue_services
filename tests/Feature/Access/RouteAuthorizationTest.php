<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Task 3.7: every admin/staff route has an explicit expectation per role.
 * Adding a route under /admin or /staff without listing it here fails
 * test_every_admin_and_staff_route_is_covered.
 */
class RouteAuthorizationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private const ALL = [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER, Roles::RECEPTIONIST, Roles::EMPLOYEE];

    /**
     * Route name => [method, uri (with {location} placeholder if needed), roles allowed].
     *
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function routeMatrix(): array
    {
        return [
            'admin.home' => ['get', '/admin', Roles::ADMIN_CONSOLE_ROLES],
            'admin.branding' => ['get', '/admin/branding', [Roles::COMPANY_ADMIN]],
            'admin.audit' => ['get', '/admin/audit', [Roles::COMPANY_ADMIN]],
            'admin.usage' => ['get', '/admin/usage', [Roles::COMPANY_ADMIN]],
            'admin.usage.export' => ['get', '/admin/usage/export', [Roles::COMPANY_ADMIN]],
            'admin.reports' => ['get', '/admin/reports', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]],
            'admin.reports.export' => ['get', '/admin/reports/export?type=summary&from=2026-01-01&to=2026-01-31', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]],
            'admin.overview' => ['get', '/admin/overview', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]],
            'admin.operations' => ['get', '/admin/operations', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]],
            'admin.feedback' => ['get', '/admin/feedback', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]],
            'staff.feedback' => ['get', '/staff/my-feedback', []], // off by default (company setting)
            'admin.signage' => ['get', '/admin/signage', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]],
            'admin.devices' => ['get', '/admin/devices', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]],
            'admin.sms' => ['get', '/admin/sms', [Roles::COMPANY_ADMIN]],
            'admin.sms.log' => ['get', '/admin/sms/log', [Roles::COMPANY_ADMIN]],
            'admin.locations' => ['get', '/admin/locations', Roles::ADMIN_CONSOLE_ROLES],
            'admin.locations.show' => ['get', '/admin/locations/{location}', Roles::ADMIN_CONSOLE_ROLES],
            'admin.locations.qr' => ['get', '/admin/locations/{location}/qr.svg', Roles::ADMIN_CONSOLE_ROLES],
            'admin.locations.poster' => ['get', '/admin/locations/{location}/poster', Roles::ADMIN_CONSOLE_ROLES],
            'admin.services' => ['get', '/admin/services', Roles::ADMIN_CONSOLE_ROLES],
            'admin.employees.availability' => ['get', '/admin/employees/{employee}/availability', Roles::ADMIN_CONSOLE_ROLES],
            'admin.employees' => ['get', '/admin/employees', Roles::ADMIN_CONSOLE_ROLES],
            'staff.home' => ['get', '/staff', self::ALL],
            'staff.location.switch' => ['post', '/staff/location/{location}', self::ALL],
            'staff.queue.snapshot' => ['get', '/staff/queue/snapshot', self::ALL],
            'staff.appointments' => ['get', '/staff/appointments', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER, Roles::RECEPTIONIST]],
            'staff.availability' => ['get', '/staff/my-availability', self::ALL],
            'staff.checkin' => ['get', '/staff/checkin', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER, Roles::RECEPTIONIST]],
        ];
    }

    public function test_every_admin_and_staff_route_is_covered(): void
    {
        $names = collect(RouteFacade::getRoutes()->getRoutes())
            ->map(fn (Route $r) => $r->getName())
            ->filter(fn ($n) => $n && (str_starts_with($n, 'admin.') || str_starts_with($n, 'staff.')))
            ->values()->all();

        $missing = array_diff($names, array_keys(self::routeMatrix()));
        $this->assertSame([], array_values($missing), 'Add these routes to RouteAuthorizationTest::routeMatrix(): '.implode(', ', $missing));
    }

    /** @return iterable<string, array{string}> */
    public static function routes(): iterable
    {
        foreach (array_keys(self::routeMatrix()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('routes')]
    public function test_route_access_per_role(string $name): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$method, $uri, $allowed] = self::routeMatrix()[$name];
        [$a] = $this->twoTenants();

        $location = $this->inTenant($a, fn () => Location::factory()->create());
        $uri = $this->fill($uri, $a, $location);

        // Guests are sent to sign-in.
        $this->{$method}($uri)->assertRedirect('/login');

        foreach (self::ALL as $role) {
            $user = $this->inTenant($a, function () use ($a, $role, $location) {
                $u = $this->userIn($a);
                $u->assignRole($role);
                $u->locations()->attach($location);

                return $u;
            });

            $this->tenantContext()->clear();
            $status = $this->actingAs($user)->from('/staff')->{$method}($uri)->getStatusCode();

            if (in_array($role, $allowed, true)) {
                $this->assertNotSame(403, $status, "{$role} should reach {$name}");
                $this->assertLessThan(500, $status, "{$role} got {$status} on {$name}");
            } else {
                $this->assertSame(403, $status, "{$role} must be forbidden from {$name}");
            }
        }
    }

    #[DataProvider('routes')]
    public function test_user_without_any_role_is_forbidden(string $name): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$method, $uri] = self::routeMatrix()[$name];
        [$a] = $this->twoTenants();
        $location = $this->inTenant($a, fn () => Location::factory()->create());
        $uri = $this->fill($uri, $a, $location);

        $this->actingAs($this->userIn($a))->{$method}($uri)->assertForbidden();
    }

    /** Replace {location} / {employee} placeholders with records of the tenant. */
    private function fill(string $uri, Tenant $tenant, Location $location): string
    {
        if (str_contains($uri, '{employee}')) {
            $employee = $this->inTenant($tenant, function () use ($location) {
                $e = Employee::factory()->create();
                $e->user->locations()->attach($location);

                return $e;
            });
            $uri = str_replace('{employee}', (string) $employee->id, $uri);
        }

        return str_replace('{location}', (string) $location->id, $uri);
    }

    public function test_platform_admin_is_forbidden_from_tenant_routes_without_support_session(): void
    {
        $root = User::factory()->create();
        $root->forceFill(['is_platform_admin' => true])->save();

        foreach (self::routeMatrix() as [$method, $uri]) {
            if (! str_contains($uri, '{')) {
                $this->actingAs($root)->{$method}($uri)->assertForbidden();
            }
        }
    }
}
