<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 bg-[radial-gradient(ellipse_at_top,_var(--color-brand-100),_transparent_60%)] px-4 py-12 text-slate-900 antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-3">
            <span class="grid size-11 place-items-center rounded-xl bg-brand-600 text-lg font-bold text-white shadow-md shadow-brand-600/20" aria-hidden="true">{{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}</span>
            <p class="text-xl font-semibold tracking-tight">{{ config('app.name') }}</p>
        </div>
        <div class="card p-7 shadow-lg shadow-slate-200/60">
            @yield('content')
        </div>
    </div>
</body>
</html>
