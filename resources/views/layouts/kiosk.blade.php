@php
    $tenant = app(\App\Domain\Tenancy\TenantContext::class)->get();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? __('Check-in') }}</title>
    <style>:root { --brand: {{ $tenant?->primary_color ?? '#1d4ed8' }}; --brand-accent: {{ $tenant?->accent_color ?? '#f59e0b' }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{-- No links or browser chrome: the kiosk runs in browser kiosk/full-screen mode. --}}
<body class="min-h-screen select-none overscroll-none bg-slate-100 text-slate-900 antialiased" oncontextmenu="return false">
    {{ $slot }}
    @livewireScripts
</body>
</html>
