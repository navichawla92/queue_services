@extends('layouts.guest', ['title' => __('Choose a new password')])

@section('content')
    <h1 class="mb-4 text-lg font-semibold">{{ __('Choose a new password') }}</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('New password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 text-white">{{ __('Reset password') }}</button>
    </form>
@endsection
