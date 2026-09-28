@extends('layouts.app', ['title' => __('Platform')])

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('Tenants') }}</h1>

    <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('Name') }}</th>
                    <th class="px-3 py-2">{{ __('Plan') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Support') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($tenants as $tenant)
                    <tr>
                        <td class="px-3 py-2">{{ $tenant->name }}</td>
                        <td class="px-3 py-2">{{ $tenant->plan->name }}</td>
                        <td class="px-3 py-2">{{ $tenant->status }}</td>
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('platform.support.start', $tenant) }}" class="flex gap-2">
                                @csrf
                                <input name="reason" required minlength="5" placeholder="{{ __('Reason for access') }}" class="rounded border border-slate-300 px-2 py-1">
                                <button class="rounded bg-amber-500 px-3 py-1 text-white">{{ __('Open support session') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @error('reason') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
@endsection
