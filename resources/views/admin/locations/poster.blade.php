<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Check-in poster') }} — {{ $location->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; color: #0f172a; text-align: center; }
        .brand { background: {{ $tenant->primary_color }}; color: #fff; padding: 24px; border-radius: 12px; }
        .brand img { max-height: 70px; background: #fff; border-radius: 8px; padding: 6px; }
        h1 { font-size: 44px; margin: 36px 0 8px; }
        .loc { font-size: 22px; color: #475569; margin: 0; }
        .qr svg { width: 110mm; height: 110mm; margin: 24px auto; display: block; }
        .steps { font-size: 20px; line-height: 1.6; }
        .url { font-family: monospace; font-size: 13px; color: #64748b; word-break: break-all; margin-top: 16px; }
        .no-print { margin: 16px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">{{ __('Print') }}</button></div>

    <div class="brand">
        @if ($tenant->logo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($tenant->logo_path) }}" alt="">
        @endif
        <div style="font-size: 26px; font-weight: 600; margin-top: 8px">{{ $tenant->brandName() }}</div>
    </div>

    <h1>{{ __('Skip the line — check in here') }}</h1>
    <p class="loc">{{ $location->name }}</p>

    <div class="qr">{!! $qrSvg !!}</div>

    <div class="steps">
        {{ __('1. Scan the code with your phone camera') }}<br>
        {{ __('2. Enter your name and choose a service') }}<br>
        {{ __('3. We will text you when it is your turn') }}
    </div>
    <div class="url">{{ $location->checkinUrl(qr: true) }}</div>
</body>
</html>
