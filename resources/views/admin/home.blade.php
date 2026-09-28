@extends('layouts.app', ['title' => __('Administration')])

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('Administration') }}</h1>
    <ul class="mt-6 space-y-2">
        @can('tenant.manage')
            <li><a class="underline" href="{{ route('admin.branding') }}">{{ __('Branding') }}</a></li>
        @endcan
        @can('displays.manage')
            <li><a class="underline" href="{{ route('admin.devices') }}">{{ __('Kiosks & lobby displays') }}</a></li>
        @endcan
        @can('audit.view')
            <li><a class="underline" href="{{ route('admin.audit') }}">{{ __('Audit log') }}</a></li>
        @endcan
    </ul>
@endsection
