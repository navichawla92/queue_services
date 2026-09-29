@extends('layouts.guest', ['title' => __('Reset password')])

@section('content')
    <h1 class="mb-1 text-lg font-semibold tracking-tight">{{ __('Reset password') }}</h1>
    <p class="mb-4 text-sm text-slate-600">{{ __('Enter your email and we will send you a reset link.') }}</p>

    @if (session('status'))
        <p class="alert-success mb-4">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="input mt-1 w-full">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary w-full">{{ __('Email reset link') }}</button>
    </form>

    <p class="mt-5 text-center text-sm">
        <a href="{{ route('login') }}" class="text-slate-500 hover:text-brand-600 hover:underline">{{ __('Back to sign in') }}</a>
    </p>
@endsection
