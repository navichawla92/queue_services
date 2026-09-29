@extends('layouts.app', ['title' => __('Staff')])

@section('content')
    <div class="space-y-6">
        <h1 class="page-title">{{ __('Staff console') }}</h1>
        <livewire:staff.my-status />
        <p class="text-slate-600">{{ __('The live queue dashboard will appear here.') }}</p>
    </div>
@endsection
