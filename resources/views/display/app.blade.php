<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $device->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/display.js'])
    <style>
        /* TV-friendly: sizes scale with the screen (720p … 4K, landscape or portrait). */
        html, body { height: 100%; cursor: none; overflow: hidden; }
        .fs-xs { font-size: clamp(0.8rem, 1.1vmin + 0.4rem, 2.2rem); }
        .fs-sm { font-size: clamp(1rem, 1.6vmin + 0.4rem, 3rem); }
        .fs-md { font-size: clamp(1.3rem, 2.4vmin + 0.5rem, 4.5rem); }
        .fs-lg { font-size: clamp(1.8rem, 4vmin + 0.5rem, 7rem); }
        .fs-xl { font-size: clamp(3rem, 11vmin, 20rem); }
        @keyframes pulse-in { 0% { transform: scale(.85); opacity: 0 } 60% { transform: scale(1.04); opacity: 1 } 100% { transform: scale(1) } }
        .call-in { animation: pulse-in .6s ease-out; }
        @keyframes ticker { from { transform: translateX(100%) } to { transform: translateX(-100%) } }
        .ticker-run { display: inline-block; white-space: nowrap; animation: ticker 30s linear infinite; }
    </style>
</head>
<body class="bg-slate-950 text-white antialiased">
<div x-data="lobbyDisplay({
        snapshotUrl: @js(route('display.snapshot')),
        pairUrl: @js(route('display.shell')),
        channelKey: @js($channelKey),
     })"
     class="flex h-full flex-col" data-testid="lobby-display">

    <template x-if="!snap">
        <div class="flex h-full items-center justify-center fs-md text-slate-400">{{ __('Loading…') }}</div>
    </template>

    <template x-if="snap">
        <div class="flex h-full flex-col">
            {{-- Header: brand + clock --}}
            <header x-show="snap.config.header" class="flex items-center justify-between bg-[var(--brand)] px-[3vmin] py-[1.5vmin]">
                <div class="flex items-center gap-[2vmin]">
                    <template x-if="snap.brand.logo"><img :src="snap.brand.logo" alt="" class="h-[6vmin] rounded bg-white/90 p-1"></template>
                    <span class="fs-md font-semibold" x-text="snap.brand.name"></span>
                    <span class="fs-sm text-white/70" x-text="snap.location.name"></span>
                </div>
                <div class="flex items-center gap-[2vmin]">
                    <span x-show="stale" class="fs-xs rounded bg-black/30 px-2 py-1 text-white/80" data-testid="offline">{{ __('Offline — showing last update') }}</span>
                    <span class="fs-lg font-semibold tabular-nums" x-text="clock"></span>
                </div>
            </header>

            {{-- Zones --}}
            <main class="grid min-h-0 flex-1 gap-[1.5vmin] p-[1.5vmin]"
                  :class="{
                    'grid-cols-1': snap.config.layout === 'queue' || snap.config.orientation === 'portrait',
                    'grid-cols-[minmax(0,2fr)_minmax(0,3fr)]': snap.config.layout === 'split' && snap.config.orientation === 'landscape' && snap.config.queue_zone === 'left',
                    'grid-cols-[minmax(0,3fr)_minmax(0,2fr)]': snap.config.layout === 'split' && snap.config.orientation === 'landscape' && snap.config.queue_zone === 'right',
                    'grid-rows-[auto_minmax(0,1fr)]': snap.config.layout === 'split' && snap.config.orientation === 'portrait',
                  }">

                {{-- Queue zone --}}
                <section class="flex min-h-0 flex-col gap-[1.5vmin]"
                         :class="{ 'order-2': snap.config.layout === 'split' && snap.config.queue_zone === 'right' && snap.config.orientation === 'landscape' }">
                    <div class="rounded-2xl bg-white/5 p-[2vmin]">
                        <h2 class="fs-sm mb-[1vmin] uppercase tracking-widest text-white/60">{{ __('Now serving') }}</h2>
                        <div class="grid gap-[1vmin]" :class="snap.config.layout === 'queue' ? 'grid-cols-2' : 'grid-cols-1'">
                            <template x-for="t in snap.serving" :key="t.call_key">
                                <div class="flex items-center justify-between rounded-xl bg-white/10 px-[2vmin] py-[1.2vmin]" :style="`border-left: .8vmin solid ${t.color}`" data-testid="now-serving">
                                    <div>
                                        <span class="fs-lg font-black tracking-wider" x-text="t.number"></span>
                                        <span class="fs-sm ml-[1vmin] text-white/70" x-show="t.name" x-text="t.name"></span>
                                    </div>
                                    <div class="text-right">
                                        <div class="fs-md font-semibold" x-text="t.desk ? '→ ' + t.desk : ''"></div>
                                        <div class="fs-xs text-white/60" x-show="t.employee" x-text="t.employee"></div>
                                    </div>
                                </div>
                            </template>
                            <p x-show="snap.serving.length === 0" class="fs-sm text-white/40">{{ __('Please wait to be called') }}</p>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden rounded-2xl bg-white/5 p-[2vmin]" x-show="snap.config.waiting_rows > 0">
                        <h2 class="fs-sm mb-[1vmin] uppercase tracking-widest text-white/60">{{ __('Waiting') }}</h2>
                        <div class="grid gap-[0.8vmin]" :class="snap.config.layout === 'queue' ? 'grid-cols-3' : 'grid-cols-2'">
                            <template x-for="t in snap.waiting" :key="t.number">
                                <div class="fs-md rounded-lg bg-white/5 px-[1.5vmin] py-[0.8vmin]" :style="`border-left: .5vmin solid ${t.color}`">
                                    <span class="font-bold" x-text="t.number"></span>
                                    <span class="fs-xs text-white/60" x-show="t.name" x-text="t.name"></span>
                                </div>
                            </template>
                        </div>
                        <p x-show="snap.waiting_more > 0" class="fs-sm mt-[1vmin] text-white/60" x-text="`+${snap.waiting_more} {{ __('more waiting') }}`"></p>
                        <div x-show="snap.config.show_avg_wait && snap.avg_wait.length" class="fs-xs mt-[1.5vmin] flex flex-wrap gap-[2vmin] text-white/60">
                            <template x-for="d in snap.departments" :key="d.id">
                                <span x-show="avgWaitFor(d.id) > 0" x-text="`${d.name}: ~${avgWaitFor(d.id)} min`"></span>
                            </template>
                        </div>
                    </div>
                </section>

                {{-- Signage zone (digital signage player mounts here) --}}
                <section x-show="snap.config.layout === 'split'" id="signage-zone" class="relative min-h-0 overflow-hidden rounded-2xl bg-black"
                         data-testid="signage-zone"></section>
            </main>

            {{-- Ticker --}}
            <footer x-show="snap.config.ticker && snap.ticker.length" class="overflow-hidden bg-[var(--brand-accent)] py-[1vmin] text-slate-900">
                <div class="ticker-run fs-md font-semibold" x-text="snap.ticker.join('   •   ')"></div>
            </footer>
            <div x-show="snap.brand.powered_by" class="fs-xs absolute bottom-1 right-2 text-white/30" x-text="'Powered by ' + snap.brand.powered_by"></div>
        </div>
    </template>

    {{-- Call highlight (takes priority over everything) --}}
    <template x-if="highlight">
        <div class="call-in fixed inset-0 z-50 flex flex-col items-center justify-center bg-[var(--brand)]" data-testid="call-highlight">
            <p class="fs-md uppercase tracking-[0.3em] text-white/80">{{ __('Now calling') }}</p>
            <p class="fs-xl font-black tracking-wider" x-text="highlight.number"></p>
            <p class="fs-lg" x-show="highlight.name" x-text="highlight.name"></p>
            <p class="fs-lg mt-[2vmin] font-semibold" x-show="highlight.desk" x-text="'{{ __('Please go to') }} ' + highlight.desk"></p>
        </div>
    </template>

    {{-- Browsers need one interaction before sound can play. --}}
    <button x-show="needsTap && snap?.config.chime" @click="unlockAudio()" style="cursor: pointer"
            class="fs-xs fixed bottom-4 left-4 z-40 rounded bg-white/10 px-4 py-2 text-white/70">{{ __('Tap to enable sound') }}</button>
</div>
</body>
</html>
