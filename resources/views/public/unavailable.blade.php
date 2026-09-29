@extends('layouts.public', ['title' => __('Service unavailable')])

@section('content')
    <div class="card p-8 text-center">
        <h1 class="page-title">{{ __('Service unavailable') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('This service is currently unavailable. Please see a member of staff.') }}</p>
    </div>
@endsection
