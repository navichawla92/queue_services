<div
    x-data="{
        idle: null,
        arm() {
            clearTimeout(this.idle);
            const seconds = $wire.step === 'done' ? {{ $confirmationSeconds }} : {{ $idleSeconds }};
            if ($wire.step === 'start' && !$wire.largeText && !$wire.highContrast && $wire.locale === 'en') return;
            this.idle = setTimeout(() => $wire.idleReset(), seconds * 1000);
        },
    }"
    x-init="arm(); $wire.$hook('commit', ({ succeed }) => succeed(() => $nextTick(() => arm())))"
    @pointerdown.window="arm()" @keydown.window="arm()"
    @class([
        'flex min-h-screen flex-col',
        'text-[1.25em]' => $largeText,
        'bg-black text-yellow-300 [&_button]:border-yellow-300 [&_input]:bg-black [&_input]:text-yellow-300' => $highContrast,
    ])
    data-testid="kiosk"
>
    <header class="flex items-center justify-between bg-[var(--brand)] px-8 py-4 text-white">
        <div class="flex items-center gap-4">
            @if ($tenant->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($tenant->logo_path) }}" alt="" class="h-12 rounded bg-white/90 p-1">
            @endif
            <span class="text-2xl font-semibold">{{ $tenant->brandName() }}</span>
        </div>
        <div class="flex items-center gap-3 text-lg">
            @foreach ($languages as $lang)
                <button wire:click="setLocale('{{ $lang }}')" @class(['min-h-12 min-w-12 rounded-lg px-3', 'bg-white text-slate-900' => $locale === $lang, 'bg-white/10' => $locale !== $lang])>{{ strtoupper($lang) }}</button>
            @endforeach
            <button wire:click="toggleLargeText" class="min-h-12 rounded-lg bg-white/10 px-3" aria-pressed="{{ $largeText ? 'true' : 'false' }}">A+</button>
            <button wire:click="toggleContrast" class="min-h-12 rounded-lg bg-white/10 px-3" aria-pressed="{{ $highContrast ? 'true' : 'false' }}">{{ __('Contrast') }}</button>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col justify-center px-8 py-10">
        @include('livewire.public-site.partials.checkin-steps', ['big' => true])
    </main>
</div>
