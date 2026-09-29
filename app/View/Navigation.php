<?php

namespace App\View;

use App\Domain\Billing\Features;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;

/**
 * Sidebar menu for the staff/admin app. Single source for the layout sidebar
 * and the admin overview page. Items are filtered by permission; items whose
 * plan feature is off stay visible but are marked `locked`.
 */
class Navigation
{
    /** Heroicons (outline, 24px) path data. */
    private const ICONS = [
        'queue' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z',
        'checkin' => 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z',
        'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
        'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'chat' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
        'home' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'pin' => 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
        'squares' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
        'users' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
        'columns' => 'M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z',
        'chart' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
        'bolt' => 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
        'tv' => 'M6 20.25h12m-7.5-3v3m3-3v3m-10.125-3h17.25c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125Z',
        'tablet' => 'M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z',
        'phone' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
        'cog' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'card' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
        'document' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        'lock' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
    ];

    public function __construct(private readonly Features $features) {}

    public static function icon(string $name): string
    {
        return self::ICONS[$name] ?? '';
    }

    /**
     * @return list<array{key: string, title: string, items: list<array{label: string, url: ?string, active: bool, locked: bool, feature: ?string, icon: string, description: string, badge: int}>}>
     */
    public function sections(User $user): array
    {
        // Self-service pages are opt-in per company; mirror the checks in
        // EmployeeAvailability::authorizeEmployee() and FeedbackReview::authorizeView().
        $isEmployee = $user->employee()->exists();
        $settings = app(TenantContext::class)->require()->settings();
        $ownSchedule = $isEmployee && ($user->can('setup.manage') || (bool) $settings->get('employees_edit_own_schedule', false));
        $ownFeedback = $isEmployee && (bool) $settings->get('employees_view_own_feedback', false);
        $lowFeedback = $user->can('feedback.view')
            ? $user->unreadNotifications()->where('data->kind', 'low_feedback')->count()
            : 0;

        // [route, active pattern, label, description, permission|null, plan feature|null, icon]
        $definitions = [
            'workspace' => [__('Workspace'), [
                ['staff.home', 'staff.home', __('Queue'), '', 'access-staff', null, 'queue'],
                ['staff.checkin', 'staff.checkin', __('Check in'), '', 'checkin.create', null, 'checkin'],
                ['staff.appointments', 'staff.appointments', __('Appointments'), '', 'appointments.manage', 'appointments', 'calendar'],
                ['staff.availability', 'staff.availability', __('My availability'), '', $ownSchedule ? 'access-staff' : false, null, 'clock'],
                ['staff.feedback', 'staff.feedback', __('My feedback'), '', $ownFeedback ? 'access-staff' : false, 'feedback', 'chat'],
            ]],
            'setup' => [__('Setup'), [
                ['admin.home', 'admin.home', __('Overview'), '', 'access-admin', null, 'home'],
                ['admin.locations', 'admin.locations*', __('Locations'), __('Departments, desks, services offered, hours, closures, check-in QR'), 'setup.manage', null, 'pin'],
                ['admin.services', 'admin.services', __('Services'), __('Service catalog and durations'), 'setup.manage', null, 'squares'],
                ['admin.employees', 'admin.employees*', __('Staff'), __('Accounts, roles, departments and skills'), 'setup.manage', null, 'users'],
            ]],
            'insights' => [__('Insights'), [
                ['admin.overview', 'admin.overview', __('All locations'), __('Live status and KPIs side by side'), 'reports.view', 'advanced_analytics', 'columns'],
                ['admin.reports', 'admin.reports*', __('Reports'), __('KPIs, trends, peak hours, breakdowns, CSV export'), 'reports.view', 'advanced_analytics', 'chart'],
                ['admin.operations', 'admin.operations', __('Live operations'), __('Waiting now, longest wait, staff, SLA breaches'), 'reports.view', null, 'bolt'],
                ['admin.feedback', 'admin.feedback', __('Customer feedback'), __('Ratings and comments by employee, department and location'), 'feedback.view', 'feedback', 'chat'],
            ]],
            'channels' => [__('Channels'), [
                ['admin.signage', 'admin.signage', __('Digital signage'), __('Slides, videos, playlists, schedules and ticker'), 'signage.manage', 'signage', 'tv'],
                ['admin.devices', 'admin.devices', __('Kiosks & displays'), __('Pair and revoke devices'), 'displays.manage', null, 'tablet'],
                ['admin.sms', 'admin.sms*', __('SMS notifications'), __('Provider, messages, templates and delivery log'), 'notifications.manage', null, 'phone'],
            ]],
            'account' => [__('Account'), [
                ['admin.branding', 'admin.branding', __('Company settings'), __('Branding and staff self-service'), 'tenant.manage', null, 'cog'],
                ['admin.usage', 'admin.usage*', __('Plan & usage'), __('Your plan, limits and this month\'s usage'), 'tenant.manage', null, 'card'],
                ['admin.audit', 'admin.audit', __('Audit log'), __('Who changed what, and when'), 'audit.view', null, 'document'],
            ]],
        ];

        $sections = [];
        foreach ($definitions as $key => [$title, $rows]) {
            $items = [];
            foreach ($rows as [$route, $pattern, $label, $description, $permission, $feature, $icon]) {
                if ($permission === false || ($permission !== null && ! $user->can($permission))) {
                    continue;
                }
                $locked = $feature !== null && ! $this->features->enabled($feature);
                // Unread low-rating alerts jump straight to the filtered list.
                $badge = $route === 'admin.feedback' ? $lowFeedback : 0;
                $items[] = [
                    'label' => $label,
                    'url' => $locked ? null : route($route, $badge ? ['maxRating' => 2] : []),
                    'active' => request()->routeIs($pattern),
                    'locked' => $locked,
                    'feature' => $feature,
                    'icon' => self::ICONS[$icon],
                    'description' => $description,
                    'badge' => $badge,
                ];
            }
            if ($items) {
                $sections[] = ['key' => $key, 'title' => $title, 'items' => $items];
            }
        }

        return $sections;
    }
}
