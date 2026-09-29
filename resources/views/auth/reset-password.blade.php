@extends('layouts.guest', ['title' => __('Choose a new password')])

@section('content')
    <h1 class="mb-5 text-lg font-semibold tracking-tight">{{ __('Choose a new password') }}</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required
                   class="input mt-1 w-full">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="form-label">{{ __('New password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="input mt-1 w-full">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="input mt-1 w-full">
        </div>
        <button type="submit" class="btn btn-primary w-full">{{ __('Reset password') }}</button>
    </form>
@endsection
