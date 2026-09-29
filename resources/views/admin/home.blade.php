@extends('layouts.app', ['title' => __('Administration')])

@php
    // Same entries as the sidebar, minus the staff workspace and this page itself.
    $sections = collect(app(\App\View\Navigation::class)->sections(auth()->user()))
        ->reject(fn ($s) => $s['key'] === 'workspace')
        ->map(fn ($s) => [...$s, 'items' => array_values(array_filter($s['items'], fn ($i) => $i['description'] !== ''))])
        ->filter(fn ($s) => $s['items']);
@endphp

@section('content')
    <div>
        <h1 class="page-title">{{ __('Administration') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Everything here is also available from the menu on the left.') }}</p>
    </div>

    <div class="mt-8 space-y-8">
        @foreach ($sections as $section)
            <section>
                <h2 class="mb-3 text-xs font-semibold tracking-wider text-slate-400 uppercase">{{ $section['title'] }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($section['items'] as $item)
                        @if (! $item['locked'])
                            <a href="{{ $item['url'] }}" class="group card flex items-start gap-3.5 p-4 transition hover:border-brand-200 hover:shadow-md">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white" aria-hidden="true">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" /></svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-slate-900">{{ $item['label'] }}</span>
                                    <span class="mt-0.5 block text-sm text-slate-500">{{ $item['description'] }}</span>
                                </span>
                            </a>
                        @else
                            <div class="flex items-start gap-3.5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4" data-testid="gated-{{ $item['feature'] }}">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-200 text-slate-400" aria-hidden="true">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\View\Navigation::icon('lock') }}" /></svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2 font-semibold text-slate-500">{{ $item['label'] }} <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Upgrade') }}</span></span>
                                    <span class="mt-0.5 block text-sm text-slate-400">{{ __('Available on higher plans.') }}</span>
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
