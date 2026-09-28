@extends('layouts.guest', ['title' => __('Sign in')])

@section('content')
    <h1 class="mb-4 text-lg font-semibold">{{ __('Sign in') }}</h1>

    @if (session('status'))
        <p class="mb-4 text-sm text-green-700">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 text-white">{{ __('Sign in') }}</button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('password.request') }}" class="text-slate-600 underline">{{ __('Forgot your password?') }}</a>
    </p>
@endsection
