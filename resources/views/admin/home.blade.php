@extends('layouts.app', ['title' => __('Administration')])

@php
    $features = app(\App\Domain\Billing\Features::class);
    // [permission, route, title, description, plan feature|null]
    $cards = [
        ['setup.manage', 'admin.locations', __('Locations'), __('Departments, desks, services offered, hours, closures, check-in QR'), null],
        ['setup.manage', 'admin.services', __('Services'), __('Service catalog and durations'), null],
        ['setup.manage', 'admin.employees', __('Staff'), __('Accounts, roles, departments and skills'), null],
        ['reports.view', 'admin.overview', __('All locations'), __('Live status and KPIs side by side'), 'advanced_analytics'],
        ['reports.view', 'admin.reports', __('Reports'), __('KPIs, trends, peak hours, breakdowns, CSV export'), 'advanced_analytics'],
        ['reports.view', 'admin.operations', __('Live operations'), __('Waiting now, longest wait, staff, SLA breaches'), null],
        ['feedback.view', 'admin.feedback', __('Customer feedback'), __('Ratings and comments by employee, department and location'), 'feedback'],
        ['signage.manage', 'admin.signage', __('Digital signage'), __('Slides, videos, playlists, schedules and ticker'), 'signage'],
        ['displays.manage', 'admin.devices', __('Kiosks & lobby displays'), __('Pair and revoke devices'), null],
        ['notifications.manage', 'admin.sms', __('SMS notifications'), __('Provider, messages, templates and delivery log'), null],
        ['tenant.manage', 'admin.branding', __('Branding'), __('Logo, colors and public text'), null],
        ['tenant.manage', 'admin.usage', __('Plan & usage'), __('Your plan, limits and this month\'s usage'), null],
        ['audit.view', 'admin.audit', __('Audit log'), __('Who changed what, and when'), null],
    ];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('Administration') }}</h1>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as [$permission, $route, $title, $description, $feature])
            @can($permission)
                @if ($feature === null || $features->enabled($feature))
                    <a href="{{ route($route) }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                        <div class="font-semibold">{{ $title }}</div>
                        <div class="text-sm text-slate-500">{{ $description }}</div>
                    </a>
                @else
                    <div class="rounded-lg bg-white/60 p-5 opacity-60 shadow-sm" data-testid="gated-{{ $feature }}">
                        <div class="font-semibold">{{ $title }} <span class="ml-1 rounded bg-amber-100 px-1.5 text-xs text-amber-800">{{ __('Upgrade') }}</span></div>
                        <div class="text-sm text-slate-500">{{ __('Available on higher plans.') }}</div>
                    </div>
                @endif
            @endcan
        @endforeach
    </div>
@endsection
