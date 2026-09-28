@extends('layouts.guest', ['title' => __('Reset password')])

@section('content')
    <h1 class="mb-2 text-lg font-semibold">{{ __('Reset password') }}</h1>
    <p class="mb-4 text-sm text-slate-600">{{ __('Enter your email and we will send you a reset link.') }}</p>

    @if (session('status'))
        <p class="mb-4 text-sm text-green-700">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 text-white">{{ __('Email reset link') }}</button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-slate-600 underline">{{ __('Back to sign in') }}</a>
    </p>
@endsection
