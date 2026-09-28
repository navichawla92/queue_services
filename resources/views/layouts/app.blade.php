<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <nav class="bg-slate-900 text-slate-100">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <span class="font-semibold">{{ app(\App\Domain\Tenancy\TenantContext::class)->get()?->brandName() ?? config('app.name') }}</span>
            @auth
                @if (($switchableLocations ?? collect())->count() > 1)
                    <div class="flex items-center gap-2 text-sm" data-testid="location-switcher">
                        <span class="text-slate-400">{{ __('Location') }}:</span>
                        @foreach ($switchableLocations as $loc)
                            <form method="POST" action="{{ route('staff.location.switch', $loc) }}">
                                @csrf
                                <button @class(['rounded px-2 py-1', 'bg-white text-slate-900' => $loc->is($currentLocation), 'hover:bg-slate-700' => ! $loc->is($currentLocation)])>
                                    {{ $loc->name }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @elseif ($currentLocation ?? null)
                    <span class="text-sm text-slate-300">{{ $currentLocation->name }}</span>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <span class="mr-3 text-sm text-slate-300">{{ auth()->user()->name }}</span>
                    <button class="text-sm underline">{{ __('Sign out') }}</button>
                </form>
            @endauth
        </div>
    </nav>

    @if (app(\App\Domain\Access\SupportSession::class)->isActive())
        <div class="bg-amber-400 text-amber-950" data-testid="support-banner">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 text-sm">
                <span>
                    <strong>{{ __('Support session') }}:</strong>
                    {{ __('you are viewing :tenant as a platform administrator. All actions are audited.', ['tenant' => app(\App\Domain\Tenancy\TenantContext::class)->get()->name]) }}
                    <em>({{ app(\App\Domain\Access\SupportSession::class)->reason() }})</em>
                </span>
                <form method="POST" action="{{ route('platform.support.end') }}">
                    @csrf @method('DELETE')
                    <button class="underline">{{ __('End session') }}</button>
                </form>
            </div>
        </div>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
