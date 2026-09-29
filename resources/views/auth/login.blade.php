@extends('layouts.guest', ['title' => __('Sign in')])

@section('content')
    <h1 class="mb-5 text-lg font-semibold tracking-tight">{{ __('Sign in') }}</h1>

    @if (session('status'))
        <p class="alert-success mb-4">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="input mt-1 w-full">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="input mt-1 w-full">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary w-full">{{ __('Sign in') }}</button>
    </form>

    <p class="mt-5 text-center text-sm">
        <a href="{{ route('password.request') }}" class="text-slate-500 hover:text-brand-600 hover:underline">{{ __('Forgot your password?') }}</a>
    </p>
@endsection
