<div class="space-y-6">
    <div>
        <a href="{{ route('admin.locations') }}" class="text-sm text-slate-500 underline">← {{ __('Locations') }}</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $location->name }}
            @unless ($location->is_active) <span class="ml-2 rounded bg-slate-200 px-2 py-0.5 text-sm">{{ __('Inactive') }}</span> @endunless
        </h1>
    </div>

    <nav class="flex flex-wrap gap-2 border-b border-slate-200">
        @foreach ([
            'departments' => __('Departments'),
            'desks' => __('Desks & rooms'),
            'services' => __('Services offered'),
            'routing' => __('Routing rules'),
            'hours' => __('Opening hours'),
            'closures' => __('Closures & holidays'),
            'booking' => __('Online booking'),
            'qr' => __('Check-in QR'),
        ] as $key => $label)
            @if ($key === 'booking' && ! app(\App\Domain\Billing\Features::class)->enabled('appointments')) @continue @endif
            <button wire:click="$set('tab', '{{ $key }}')" @class([
                'px-3 py-2 text-sm -mb-px border-b-2',
                'border-slate-900 font-semibold' => $tab === $key,
                'border-transparent text-slate-500' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    @switch($tab)
        @case('departments') <livewire:admin.setup.departments-editor :location-id="$location->id" :key="'dep-'.$location->id" /> @break
        @case('desks') <livewire:admin.setup.desks-editor :location-id="$location->id" :key="'desk-'.$location->id" /> @break
        @case('services') <livewire:admin.setup.location-services-editor :location-id="$location->id" :key="'svc-'.$location->id" /> @break
        @case('routing') <livewire:admin.setup.routing-rules-editor :location-id="$location->id" :key="'rr-'.$location->id" /> @break
        @case('hours') <livewire:admin.setup.hours-editor :location-id="$location->id" :key="'hrs-'.$location->id" /> @break
        @case('closures') <livewire:admin.setup.closures-editor :location-id="$location->id" :key="'clo-'.$location->id" /> @break
        @case('booking') <livewire:admin.setup.booking-settings-editor :location-id="$location->id" :key="'bk-'.$location->id" /> @break
        @case('qr')
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-600">{{ __('Customers scan this code to check in on their phone:') }}</p>
                <p class="mt-1 font-mono text-sm break-all">{{ $location->checkinUrl() }}</p>
                <img src="{{ route('admin.locations.qr', $location) }}" alt="{{ __('Check-in QR code') }}" class="mt-4 h-56 w-56">
                <a href="{{ route('admin.locations.poster', $location) }}" target="_blank" class="mt-4 inline-block rounded bg-slate-900 px-4 py-2 text-white">{{ __('Printable poster') }}</a>
            </div>
            @break
    @endswitch
</div>
