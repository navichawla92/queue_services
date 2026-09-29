<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $brand = app(\App\Domain\Tenancy\TenantContext::class)->get()?->brandName() ?? config('app.name');
    // Platform admins outside a tenant get no tenant menu.
    $hasSidebar = auth()->check() && app(\App\Domain\Tenancy\TenantContext::class)->check();
@endphp
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
    @if ($hasSidebar)
        @include('layouts.partials.sidebar')
    @endif

    <div @class(['lg:pl-64' => $hasSidebar])>
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="flex h-14 items-center justify-between gap-4 px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($hasSidebar)
                        <button type="button" class="btn btn-ghost -ml-2 p-1.5 lg:hidden" @click="sidebar = true" aria-label="{{ __('Open menu') }}">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </button>
                        <span class="truncate font-semibold tracking-tight lg:hidden">{{ $brand }}</span>
                    @else
                        <span class="flex items-center gap-2 font-semibold tracking-tight">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($brand, 0, 1)) }}</span>
                            <span class="truncate">{{ $brand }}</span>
                        </span>
                    @endif
                </div>

                @auth
                    <div class="flex items-center gap-3">
                        @if (($switchableLocations ?? collect())->count() > 1)
                            <div class="flex items-center gap-2 text-sm" data-testid="location-switcher">
                                <span class="hidden text-slate-400 sm:inline">{{ __('Location') }}:</span>
                                <div class="flex overflow-x-auto rounded-lg bg-slate-100 p-0.5">
                                    @foreach ($switchableLocations as $loc)
                                        <form method="POST" action="{{ route('staff.location.switch', $loc) }}">
                                            @csrf
                                            <button @class(['whitespace-nowrap rounded-md px-2.5 py-1 font-medium transition', 'bg-white text-slate-900 shadow-sm' => $loc->is($currentLocation), 'text-slate-500 hover:text-slate-900' => ! $loc->is($currentLocation)])>
                                                {{ $loc->name }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @elseif ($currentLocation ?? null)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"><span class="size-1.5 rounded-full bg-emerald-500"></span>{{ $currentLocation->name }}</span>
                        @endif
                        @unless ($hasSidebar)
                            <form method="POST" action="{{ route('logout') }}" class="flex items-center gap-2.5 border-l border-slate-200 pl-3">
                                @csrf
                                <span class="hidden text-sm font-medium text-slate-700 sm:inline">{{ auth()->user()->name }}</span>
                                <button class="btn btn-ghost px-2 py-1 text-xs">{{ __('Sign out') }}</button>
                            </form>
                        @endunless
                    </div>
                @endauth
            </div>
        </header>

        @if (app(\App\Domain\Access\SupportSession::class)->isActive())
            <div class="bg-amber-400 text-amber-950 shadow-sm" data-testid="support-banner">
                <div class="flex items-center justify-between gap-4 px-4 py-2 text-sm sm:px-6">
                    <span>
                        <strong>{{ __('Support session') }}:</strong>
                        {{ __('you are viewing :tenant as a platform administrator. All actions are audited.', ['tenant' => app(\App\Domain\Tenancy\TenantContext::class)->get()->name]) }}
                        <em>({{ app(\App\Domain\Access\SupportSession::class)->reason() }})</em>
                    </span>
                    <form method="POST" action="{{ route('platform.support.end') }}">
                        @csrf @method('DELETE')
                        <button class="whitespace-nowrap rounded-md bg-amber-950/10 px-2.5 py-1 font-medium hover:bg-amber-950/20">{{ __('End session') }}</button>
                    </form>
                </div>
            </div>
        @endif

        @auth
            @if (app(\App\Domain\Tenancy\TenantContext::class)->check() && auth()->user()->can('tenant.manage'))
                @php $usageWarnings = app(\App\Domain\Billing\Usage::class)->warnings(); @endphp
                @if ($usageWarnings)
                    <div class="border-b border-amber-200 bg-amber-50 text-amber-900" data-testid="usage-banner">
                        <div class="px-4 py-2 text-sm sm:px-6">
                            @foreach ($usageWarnings as $w)
                                <span class="mr-4">{{ __(':item: :used of :limit (:pct%)', ['item' => __($w['label']), 'used' => $w['used'], 'limit' => $w['limit'], 'pct' => $w['percent']]) }}</span>
                            @endforeach
                            <a href="{{ route('admin.usage') }}" class="font-medium underline">{{ __('Plan & usage') }}</a>
                        </div>
                    </div>
                @endif
            @endif
        @endauth

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    @livewireScripts
</body>
</html>
