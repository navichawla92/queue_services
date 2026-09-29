<div class="space-y-4">
    <h1 class="page-title">{{ __('Audit log') }}</h1>

    <div class="flex flex-wrap gap-3 card p-4 text-sm">
        <input type="text" wire:model.live.debounce.300ms="action" placeholder="{{ __('Action (e.g. ticket.)') }}" class="input input-sm">
        <input type="text" wire:model.live.debounce.300ms="actor" placeholder="{{ __('Actor name') }}" class="input input-sm">
        <label>{{ __('From') }} <input type="date" wire:model.live="from" class="input input-sm"></label>
        <label>{{ __('To') }} <input type="date" wire:model.live="to" class="input input-sm"></label>
    </div>

    <div class="overflow-x-auto card">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="px-3 py-2">{{ __('When (UTC)') }}</th>
                    <th class="px-3 py-2">{{ __('Actor') }}</th>
                    <th class="px-3 py-2">{{ __('Action') }}</th>
                    <th class="px-3 py-2">{{ __('Subject') }}</th>
                    <th class="px-3 py-2">{{ __('Changes') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $entry)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-3 py-2">{{ $entry->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $entry->actor_name ?? $entry->actor_type }}</td>
                        <td class="px-3 py-2 font-mono">{{ $entry->action }}</td>
                        <td class="px-3 py-2">{{ $entry->subject_type ? class_basename($entry->subject_type).' #'.$entry->subject_id : '—' }}</td>
                        <td class="px-3 py-2">
                            @foreach (array_unique(array_merge(array_keys($entry->before ?? []), array_keys($entry->after ?? []))) as $key)
                                <div><span class="text-slate-500">{{ $key }}:</span>
                                    @if (array_key_exists($key, $entry->before ?? [])) <span class="line-through text-red-700">{{ json_encode($entry->before[$key]) }}</span> @endif
                                    @if (array_key_exists($key, $entry->after ?? [])) <span class="text-green-700">{{ json_encode($entry->after[$key]) }}</span> @endif
                                </div>
                            @endforeach
                            @if ($entry->meta) <div class="text-slate-500">{{ json_encode($entry->meta) }}</div> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ __('No entries.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entries->links() }}
</div>
