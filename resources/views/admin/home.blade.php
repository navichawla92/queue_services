@extends('layouts.app', ['title' => __('Administration')])

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('Administration') }}</h1>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @can('setup.manage')
            <a href="{{ route('admin.locations') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                <div class="font-semibold">{{ __('Locations') }}</div>
                <div class="text-sm text-slate-500">{{ __('Departments, desks, services offered, hours, closures, check-in QR') }}</div>
            </a>
            <a href="{{ route('admin.services') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                <div class="font-semibold">{{ __('Services') }}</div>
                <div class="text-sm text-slate-500">{{ __('Service catalog and durations') }}</div>
            </a>
            <a href="{{ route('admin.employees') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                <div class="font-semibold">{{ __('Staff') }}</div>
                <div class="text-sm text-slate-500">{{ __('Accounts, roles, departments and skills') }}</div>
            </a>
        @endcan
        @can('displays.manage')
            <a href="{{ route('admin.devices') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                <div class="font-semibold">{{ __('Kiosks & lobby displays') }}</div>
                <div class="text-sm text-slate-500">{{ __('Pair and revoke devices') }}</div>
            </a>
        @endcan
        @can('notifications.manage')
            <a href="{{ route('admin.sms') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                <div class="font-semibold">{{ __('SMS notifications') }}</div>
                <div class="text-sm text-slate-500">{{ __('Provider, messages, templates and delivery log') }}</div>
            </a>
        @endcan
        @can('tenant.manage')
            <a href="{{ route('admin.branding') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                <div class="font-semibold">{{ __('Branding') }}</div>
                <div class="text-sm text-slate-500">{{ __('Logo, colors and public text') }}</div>
            </a>
        @endcan
        @can('audit.view')
            <a href="{{ route('admin.audit') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
                <div class="font-semibold">{{ __('Audit log') }}</div>
                <div class="text-sm text-slate-500">{{ __('Who changed what, and when') }}</div>
            </a>
        @endcan
    </div>
@endsection
