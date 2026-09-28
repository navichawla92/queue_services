<?php

namespace App\Domain\Access;

/**
 * Built-in roles and the permissions each grants (access-control spec).
 * Roles are global (tenant_id null); assignments are per tenant (spatie
 * teams keyed by tenant_id) and additionally limited by location scope.
 */
final class Roles
{
    public const COMPANY_ADMIN = 'company-admin';

    public const LOCATION_MANAGER = 'location-manager';

    public const RECEPTIONIST = 'receptionist';

    public const EMPLOYEE = 'employee';

    public const PERMISSIONS = [
        'tenant.manage',        // company settings, branding, usage
        'users.manage',         // staff accounts & role assignment
        'locations.manage',     // create / deactivate locations
        'setup.manage',         // departments, services, employees, desks, hours, routing
        'queue.manage',         // assign, transfer, call specific, hold (any ticket)
        'queue.serve',          // call next, start, complete own tickets
        'checkin.create',       // receptionist check-in
        'appointments.manage',
        'displays.manage',
        'signage.manage',
        'notifications.manage', // SMS templates & toggles
        'reports.view',
        'reports.export',
        'feedback.view',
        'audit.view',
        'stats.view_own',
    ];

    /** @return array<string, list<string>> */
    public static function matrix(): array
    {
        return [
            self::COMPANY_ADMIN => self::PERMISSIONS,
            self::LOCATION_MANAGER => [
                'setup.manage', 'queue.manage', 'queue.serve', 'checkin.create',
                'appointments.manage', 'displays.manage', 'signage.manage',
                'reports.view', 'reports.export', 'feedback.view', 'stats.view_own',
            ],
            self::RECEPTIONIST => [
                'queue.manage', 'checkin.create', 'appointments.manage', 'stats.view_own',
            ],
            self::EMPLOYEE => [
                'queue.serve', 'stats.view_own',
            ],
        ];
    }

    /** Roles whose default landing screen is the admin console. */
    public const ADMIN_CONSOLE_ROLES = [self::COMPANY_ADMIN, self::LOCATION_MANAGER];
}
