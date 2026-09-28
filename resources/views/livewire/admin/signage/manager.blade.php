<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Digital signage') }}</h1>

    <nav class="flex gap-2 border-b border-slate-200">
        @foreach (['content' => __('Content library'), 'playlists' => __('Playlists & schedules'), 'ticker' => __('Ticker')] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" @class(['-mb-px border-b-2 px-3 py-2 text-sm', 'border-slate-900 font-semibold' => $tab === $key, 'border-transparent text-slate-500' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($flash) <p class="rounded bg-green-50 p-3 text-green-800">{{ $flash }}</p> @endif

    @if ($tab === 'content')
        <form wire:submit="saveItem" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium" for="sg-type">{{ __('Type') }}</label>
                <select id="sg-type" wire:model.live="type" class="mt-1 w-full rounded border border-slate-300 px-2 py-2" @disabled($itemId)>
                    <option value="announcement">{{ __('Announcement (text)') }}</option>
                    <option value="image">{{ __('Image slide') }}</option>
                    <option value="video">{{ __('Video') }}</option>
                    <option value="qr">{{ __('QR code slide') }}</option>
                    <option value="service_info">{{ __('Service information (automatic)') }}</option>
                    <option value="rich_text">{{ __('Formatted text (Markdown)') }}</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium" for="sg-title">{{ __('Title') }}</label>
                <input id="sg-title" wire:model="title" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            @if (in_array($type, ['announcement', 'rich_text', 'qr'], true))
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium" for="sg-body">{{ $type === 'qr' ? __('Caption') : __('Text') }}</label>
                    <textarea id="sg-body" wire:model="body" rows="4" class="mt-1 w-full rounded border border-slate-300 px-2 py-2"></textarea>
                    @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            @if ($type === 'qr')
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium" for="sg-url">{{ __('Link the QR code opens') }}</label>
                    <input id="sg-url" type="url" wire:model="url" placeholder="https://" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    @error('url') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            @if (in_array($type, ['image', 'video'], true))
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium" for="sg-media">{{ $type === 'image' ? __('Image (JPG, PNG, WebP)') : __('Video (MP4, H.264)') }} · {{ __('max :mb MB', ['mb' => $maxMb]) }}</label>
                    <input id="sg-media" type="file" wire:model="media" accept="{{ $type === 'image' ? 'image/jpeg,image/png,image/webp' : 'video/mp4' }}" class="mt-1">
                    @error('media') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            @if ($type !== 'video')
                <div>
                    <label class="block text-sm font-medium" for="sg-dur">{{ __('Show for (seconds)') }}</label>
                    <input id="sg-dur" type="number" min="3" max="600" wire:model="duration_seconds" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    @error('duration_seconds') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium" for="sg-from">{{ __('Show from (optional)') }}</label>
                <input id="sg-from" type="date" wire:model="starts_on" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium" for="sg-to">{{ __('Until (optional)') }}</label>
                <input id="sg-to" type="date" wire:model="ends_on" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                @error('ends_on') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 sm:col-span-3">
                <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ $itemId ? __('Save') : __('Add content') }}</button>
                @if ($itemId) <button type="button" wire:click="resetItemForm" class="px-4 py-2">{{ __('Cancel') }}</button> @endif
            </div>
        </form>

        <table class="min-w-full rounded-lg bg-white text-sm shadow-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-3 py-2">{{ __('Title') }}</th><th class="px-3 py-2">{{ __('Type') }}</th><th class="px-3 py-2">{{ __('Dates') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $i)
                    <tr @class(['opacity-50' => ! $i->is_active])>
                        <td class="px-3 py-2">{{ $i->title }}</td>
                        <td class="px-3 py-2">{{ \Illuminate\Support\Str::headline($i->type) }}</td>
                        <td class="px-3 py-2">{{ $i->starts_on?->format('Y-m-d') ?? '…' }} – {{ $i->ends_on?->format('Y-m-d') ?? '…' }}</td>
                        <td class="space-x-3 whitespace-nowrap px-3 py-2 text-right">
                            <button wire:click="editItem({{ $i->id }})" class="underline">{{ __('Edit') }}</button>
                            <button wire:click="toggleItem({{ $i->id }})" class="underline">{{ $i->is_active ? __('Pause') : __('Resume') }}</button>
                            <button wire:click="deleteItem({{ $i->id }})" wire:confirm="{{ __('Delete this content?') }}" class="text-red-600">{{ __('Delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ __('No content yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if ($tab === 'playlists')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-3">
                <form wire:submit="createPlaylist" class="flex gap-2">
                    <input wire:model="playlistName" placeholder="{{ __('New playlist name') }}" class="flex-1 rounded border border-slate-300 px-2 py-2" aria-label="{{ __('New playlist name') }}">
                    <button class="rounded bg-slate-900 px-3 py-2 text-white">{{ __('Create') }}</button>
                </form>
                @error('playlistName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <ul class="divide-y divide-slate-100 rounded-lg bg-white shadow-sm">
                    @forelse ($playlists as $p)
                        <li><button wire:click="selectPlaylist({{ $p->id }})" @class(['w-full px-3 py-2 text-left', 'bg-slate-100 font-semibold' => $playlistId === $p->id])>{{ $p->name }} <span class="text-slate-400">({{ $p->items_count }})</span></button></li>
                    @empty
                        <li class="px-3 py-4 text-slate-500">{{ __('No playlists yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            @if ($playlist)
                <div class="space-y-4 lg:col-span-2">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="font-semibold">{{ $playlist->name }}</h2>
                            <button wire:click="deletePlaylist({{ $playlist->id }})" wire:confirm="{{ __('Delete this playlist and its schedules?') }}" class="text-sm text-red-600">{{ __('Delete playlist') }}</button>
                        </div>
                        <ol class="space-y-1 text-sm">
                            @forelse ($playlist->items as $i)
                                <li class="flex items-center justify-between rounded bg-slate-50 px-3 py-1">
                                    <span>{{ $loop->iteration }}. {{ $i->title }} <span class="text-slate-400">· {{ $i->type }}</span></span>
                                    <span class="space-x-2">
                                        <button wire:click="move({{ $i->pivot->id }}, -1)" aria-label="{{ __('Move up') }}">↑</button>
                                        <button wire:click="move({{ $i->pivot->id }}, 1)" aria-label="{{ __('Move down') }}">↓</button>
                                        <button wire:click="removeFromPlaylist({{ $i->pivot->id }})" class="text-red-600">{{ __('Remove') }}</button>
                                    </span>
                                </li>
                            @empty
                                <li class="text-slate-500">{{ __('Empty playlist.') }}</li>
                            @endforelse
                        </ol>
                        <div class="mt-3 flex gap-2">
                            <select wire:model="addItemId" class="flex-1 rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Content to add') }}">
                                <option value="">{{ __('Add content…') }}</option>
                                @foreach ($items as $i) <option value="{{ $i->id }}">{{ $i->title }}</option> @endforeach
                            </select>
                            <button wire:click="addToPlaylist" class="rounded bg-slate-900 px-3 py-1 text-white">{{ __('Add') }}</button>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h3 class="mb-2 font-semibold">{{ __('Where & when it plays') }}</h3>
                        <ul class="mb-3 space-y-1 text-sm">
                            @forelse ($playlist->schedules as $s)
                                <li class="flex justify-between rounded bg-slate-50 px-3 py-1">
                                    <span>
                                        {{ $s->device_id ? __('Display #:id', ['id' => $s->device_id]) : ($s->location_id ? ($locationNames[$s->location_id] ?? __('Location #:id', ['id' => $s->location_id])) : __('All locations')) }}
                                        @if ($s->is_default) · <strong>{{ __('default') }}</strong> @endif
                                        @if ($s->starts_on || $s->ends_on) · {{ $s->starts_on?->format('Y-m-d') ?? '…' }}–{{ $s->ends_on?->format('Y-m-d') ?? '…' }} @endif
                                        @if ($s->weekdays) · {{ collect($s->weekdays)->map(fn ($d) => $days[$d])->join(', ') }} @endif
                                        @if ($s->start_time) · {{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }} @endif
                                    </span>
                                    <button wire:click="deleteSchedule({{ $s->id }})" class="text-red-600">{{ __('Remove') }}</button>
                                </li>
                            @empty
                                <li class="text-slate-500">{{ __('Not scheduled anywhere yet.') }}</li>
                            @endforelse
                        </ul>
                        <form wire:submit="addSchedule" class="grid gap-2 text-sm sm:grid-cols-3">
                            <select wire:model.live="schedule.target" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Target') }}">
                                @if ($allowCompany) <option value="company">{{ __('All locations') }}</option> @endif
                                <option value="location">{{ __('One location') }}</option>
                                <option value="device">{{ __('One display') }}</option>
                            </select>
                            @if ($schedule['target'] === 'location')
                                <select wire:model="schedule.location_id" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Location') }}">
                                    <option value="">—</option>
                                    @foreach ($locations as $l) <option value="{{ $l->id }}">{{ $l->name }}</option> @endforeach
                                </select>
                            @elseif ($schedule['target'] === 'device')
                                <select wire:model="schedule.device_id" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Display') }}">
                                    <option value="">—</option>
                                    @foreach ($displays as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
                                </select>
                            @else <span></span> @endif
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="schedule.is_default"> {{ __('Default (when nothing else is scheduled)') }}</label>
                            <label>{{ __('From') }} <input type="date" wire:model="schedule.starts_on" class="block w-full rounded border border-slate-300 px-2 py-1"></label>
                            <label>{{ __('Until') }} <input type="date" wire:model="schedule.ends_on" class="block w-full rounded border border-slate-300 px-2 py-1"></label>
                            <div class="flex items-end gap-1">
                                <input type="time" wire:model="schedule.start_time" class="rounded border border-slate-300 px-1 py-1" aria-label="{{ __('From time') }}">–
                                <input type="time" wire:model="schedule.end_time" class="rounded border border-slate-300 px-1 py-1" aria-label="{{ __('To time') }}">
                            </div>
                            <div class="flex flex-wrap gap-2 sm:col-span-3">
                                @foreach ($days as $n => $d) <label class="flex items-center gap-1"><input type="checkbox" value="{{ $n }}" wire:model="schedule.weekdays"> {{ $d }}</label> @endforeach
                            </div>
                            @foreach (['schedule.location_id', 'schedule.device_id', 'schedule.ends_on', 'schedule.end_time', 'schedule.target'] as $f)
                                @error($f) <p class="text-red-600 sm:col-span-3">{{ $message }}</p> @enderror
                            @endforeach
                            <button class="rounded bg-slate-900 px-3 py-2 text-white sm:col-span-3">{{ __('Add schedule') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($tab === 'ticker')
        <form wire:submit="addTicker" class="flex flex-wrap gap-2 rounded-lg bg-white p-4 shadow-sm">
            <input wire:model="tickerBody" maxlength="280" placeholder="{{ __('e.g. Ask about our new savings rates') }}" class="flex-1 rounded border border-slate-300 px-2 py-2" aria-label="{{ __('Ticker text') }}">
            <select wire:model="tickerLocationId" class="rounded border border-slate-300 px-2 py-2" aria-label="{{ __('Location') }}">
                @if ($allowCompany) <option value="">{{ __('All locations') }}</option> @endif
                @foreach ($locations as $l) <option value="{{ $l->id }}">{{ $l->name }}</option> @endforeach
            </select>
            <button class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Add') }}</button>
            @error('tickerBody') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
            @error('tickerLocationId') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
        </form>
        <ul class="divide-y divide-slate-100 rounded-lg bg-white text-sm shadow-sm">
            @forelse ($tickers as $t)
                <li class="flex justify-between px-3 py-2">
                    <span>{{ $t->body }} <span class="text-slate-400">· {{ $t->location_id ? ($locationNames[$t->location_id] ?? '') : __('All locations') }}</span></span>
                    <button wire:click="deleteTicker({{ $t->id }})" class="text-red-600">{{ __('Remove') }}</button>
                </li>
            @empty
                <li class="px-3 py-4 text-slate-500">{{ __('No ticker messages.') }}</li>
            @endforelse
        </ul>
    @endif
</div>
