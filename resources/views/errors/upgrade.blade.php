@extends('layouts.app', ['title' => __('Upgrade required')])

@section('content')
    <div class="mx-auto max-w-lg card p-8 text-center" data-testid="upgrade-required">
        <h1 class="text-xl font-semibold">{{ __('Available on higher plans') }}</h1>
        <p class="mt-2 text-slate-600">{{ $message }}</p>
        @can('tenant.manage')
            <a href="{{ route('admin.usage') }}" class="btn btn-primary mt-5">{{ __('See your plan and usage') }}</a>
        @endcan
    </div>
@endsection
