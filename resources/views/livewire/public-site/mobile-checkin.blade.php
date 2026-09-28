<div class="rounded-2xl bg-white p-6 shadow-sm">
    @if (count($languages) > 1)
        <div class="mb-4 flex justify-end gap-2 text-sm">
            @foreach ($languages as $lang)
                <button wire:click="setLocale('{{ $lang }}')" @class(['rounded px-2 py-1', 'bg-slate-900 text-white' => $locale === $lang])>{{ strtoupper($lang) }}</button>
            @endforeach
        </div>
    @endif

    @include('livewire.public-site.partials.checkin-steps', ['big' => false])
</div>
