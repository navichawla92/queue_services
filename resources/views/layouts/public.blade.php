@php
    /** @var \App\Domain\Tenancy\Models\Tenant|null $tenant */
    $tenant ??= app(\App\Domain\Tenancy\TenantContext::class)->get();
    $primary = $tenant?->primary_color ?? '#1d4ed8';
    $accent = $tenant?->accent_color ?? '#f59e0b';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? $tenant?->brandName() ?? config('app.name') }}</title>
    <style>
        :root { --brand: {{ $primary }}; --brand-accent: {{ $accent }}; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased flex flex-col">
    <header class="bg-[var(--brand)] text-white">
        <div class="mx-auto max-w-3xl px-4 py-4 flex items-center gap-3">
            @if ($tenant?->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($tenant->logo_path) }}"
                     alt="{{ $tenant->brandName() }}" class="h-10 w-auto bg-white/90 rounded p-1">
            @endif
            <span class="text-lg font-semibold">{{ $tenant?->brandName() ?? config('app.name') }}</span>
        </div>
    </header>

    <main class="flex-1 mx-auto w-full max-w-3xl px-4 py-8">
        @if ($tenant?->public_text)
            <p class="mb-6 text-slate-600">{{ $tenant->public_text }}</p>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @if ($tenant && ! $tenant->isWhiteLabel())
        <footer class="py-4 text-center text-xs text-slate-400" data-testid="powered-by">
            {{ __('Powered by :app', ['app' => config('app.name')]) }}
        </footer>
    @endif

    @livewireScripts
</body>
</html>
