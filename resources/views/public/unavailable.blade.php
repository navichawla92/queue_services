@extends('layouts.public', ['title' => __('Service unavailable')])

@section('content')
    <div class="rounded-lg bg-white p-8 text-center shadow-sm">
        <h1 class="text-2xl font-semibold">{{ __('Service unavailable') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('This service is currently unavailable. Please see a member of staff.') }}</p>
    </div>
@endsection
