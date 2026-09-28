<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <p class="mb-6 text-center text-xl font-semibold">{{ config('app.name') }}</p>
        <div class="rounded-lg bg-white p-6 shadow-sm">
            @yield('content')
        </div>
    </div>
</body>
</html>
