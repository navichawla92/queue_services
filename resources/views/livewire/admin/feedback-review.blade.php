<div class="space-y-4">
    <h1 class="page-title">{{ $own ? __('My feedback') : __('Customer feedback') }}</h1>

    <div class="flex flex-wrap gap-3 card p-3 text-sm">
        @unless ($own)
            <select wire:model.live="employee" class="input input-sm" aria-label="{{ __('Employee') }}">
                <option value="">{{ __('All employees') }}</option>
                @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
            </select>
        @endunless
        <select wire:model.live="location" class="input input-sm" aria-label="{{ __('Location') }}">
            <option value="">{{ __('All locations') }}</option>
            @foreach ($locations as $l) <option value="{{ $l->id }}">{{ $l->name }}</option> @endforeach
        </select>
        <select wire:model.live="department" class="input input-sm" aria-label="{{ __('Department') }}">
            <option value="">{{ __('All departments') }}</option>
            @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
        </select>
        <select wire:model.live="service" class="input input-sm" aria-label="{{ __('Service') }}">
            <option value="">{{ __('All services') }}</option>
            @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
        </select>
        <select wire:model.live="maxRating" class="input input-sm" aria-label="{{ __('Rating') }}">
            <option value="">{{ __('Any rating') }}</option>
            @foreach ([1, 2, 3, 4] as $r) <option value="{{ $r }}">≤ {{ $r }}★</option> @endforeach
        </select>
        <label>{{ __('From') }} <input type="date" wire:model.live="from" class="input input-sm"></label>
        <label>{{ __('To') }} <input type="date" wire:model.live="to" class="input input-sm"></label>
    </div>

    <p class="text-lg" data-testid="feedback-summary">
        <strong>{{ number_format($average, 2) }}</strong>★ {{ __('average') }} · {{ trans_choice(':count response|:count responses', $count, ['count' => $count]) }}
    </p>

    <div class="overflow-x-auto card">
        <table class="data-table">
            <thead>
                <tr><th class="px-3 py-2">{{ __('When') }}</th><th class="px-3 py-2">{{ __('Rating') }}</th><th class="px-3 py-2">{{ __('Visit') }}</th><th class="px-3 py-2">{{ __('Comment') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($responses as $r)
                    <tr @class(['align-top', 'bg-red-50' => $r->rating <= 2])>
                        <td class="whitespace-nowrap px-3 py-2">{{ $r->submitted_at->setTimezone($tz)->format('Y-m-d H:i') }}</td>
                        <td class="whitespace-nowrap px-3 py-2 font-semibold">{{ str_repeat('★', $r->rating) }}<span class="text-slate-300">{{ str_repeat('★', 5 - $r->rating) }}</span>
                            @foreach ($r->answers ?? [] as $a) <div class="text-xs font-normal text-slate-500">{{ $a['question'] }}: {{ $a['rating'] }}/5</div> @endforeach
                        </td>
                        <td class="px-3 py-2">{{ $r->ticket?->number }} · {{ $r->service?->name }}<div class="text-xs text-slate-500">{{ $r->employee?->display_name ?? '—' }} · {{ $r->department?->name }} · {{ $r->location->name }}</div></td>
                        <td class="max-w-md px-3 py-2">{{ $r->comment }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ __('No feedback yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $responses->links() }}
</div>
