{{-- Left navigation for the staff/admin app. Menu items come from App\View\Navigation. --}}
<aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 max-lg:-translate-x-full"
       :class="{ 'translate-x-0!': sidebar }" data-testid="sidebar">
    <div class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-slate-200 px-5">
        <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-2 font-semibold tracking-tight">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($brand, 0, 1)) }}</span>
            <span class="truncate">{{ $brand }}</span>
        </a>
        <button type="button" class="btn btn-ghost -mr-2 p-1.5 lg:hidden" @click="sidebar = false" aria-label="{{ __('Close menu') }}">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @foreach (app(\App\View\Navigation::class)->sections(auth()->user()) as $section)
            <div>
                <p class="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">{{ $section['title'] }}</p>
                <ul class="space-y-0.5">
                    @foreach ($section['items'] as $item)
                        <li>
                            @if ($item['locked'])
                                <span class="side-link side-link-locked" title="{{ __('Available on higher plans.') }}">
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" /></svg>
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-label="{{ __('Upgrade') }}"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\View\Navigation::icon('lock') }}" /></svg>
                                </span>
                            @else
                                <a href="{{ $item['url'] }}" @class(['side-link', 'side-link-active' => $item['active']]) @if ($item['active']) aria-current="page" @endif>
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" /></svg>
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if ($item['badge'])
                                        <span class="rounded-full bg-red-500 px-1.5 py-0.5 text-[11px] leading-none font-semibold text-white" data-testid="feedback-alerts"
                                              title="{{ trans_choice(':count low rating|:count low ratings', $item['badge'], ['count' => $item['badge']]) }}">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="flex shrink-0 items-center gap-3 border-t border-slate-200 px-4 py-3">
        @csrf
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-slate-800">{{ auth()->user()->name }}</span>
            <span class="block truncate text-xs text-slate-500">{{ auth()->user()->email }}</span>
        </span>
        <button class="btn btn-ghost p-1.5" title="{{ __('Sign out') }}" aria-label="{{ __('Sign out') }}">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
        </button>
    </form>
</aside>

<div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>
