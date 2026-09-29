<div class="max-w-2xl space-y-6">
    <h1 class="page-title">{{ __('Company settings') }}</h1>

    <form wire:submit="save" class="space-y-5 card p-6">
        <div>
            <h2 class="font-semibold">{{ __('Branding') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Shown on public pages, kiosks and lobby displays.') }}</p>
        </div>

        <div>
            <label class="form-label" for="display_name">{{ __('Display name') }}</label>
            <input id="display_name" type="text" wire:model="display_name" placeholder="{{ $tenant->name }}"
                   class="input mt-1 w-full">
            @error('display_name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="primary_color">{{ __('Primary color') }}</label>
                <input id="primary_color" type="color" wire:model="primary_color" class="mt-1 h-10 w-full">
                @error('primary_color') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="accent_color">{{ __('Accent color') }}</label>
                <input id="accent_color" type="color" wire:model="accent_color" class="mt-1 h-10 w-full">
                @error('accent_color') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="form-label" for="public_text">{{ __('Welcome text on public pages') }}</label>
            <textarea id="public_text" wire:model="public_text" rows="3" class="input mt-1 w-full"></textarea>
            @error('public_text') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label" for="logo">{{ __('Logo (PNG, JPG or WebP, max 2 MB)') }}</label>
            @if ($tenant->logo_path)
                <div class="mt-2 flex items-center gap-3">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($tenant->logo_path) }}" class="h-12" alt="">
                    <button type="button" wire:click="removeLogo" class="text-sm text-red-600">{{ __('Remove') }}</button>
                </div>
            @endif
            <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="mt-2">
            @error('logo') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            @if ($saved) <span class="text-sm text-green-700">{{ __('Saved.') }}</span> @endif
        </div>
    </form>

    <form wire:submit="saveSelfService" class="space-y-4 card p-6" data-testid="self-service-settings">
        <div>
            <h2 class="font-semibold">{{ __('Staff self-service') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Pages employees can open from their own menu.') }}</p>
        </div>

        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="employees_edit_own_schedule" class="mt-1">
            <span>
                <span class="block text-sm font-medium text-slate-700">{{ __('Employees can edit their own availability') }}</span>
                <span class="block text-sm text-slate-500">{{ __('Weekly working hours and time off, used for appointment booking.') }}</span>
            </span>
        </label>

        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="employees_view_own_feedback" class="mt-1">
            <span>
                <span class="block text-sm font-medium text-slate-700">{{ __('Employees can see their own customer feedback') }}</span>
                <span class="block text-sm text-slate-500">{{ __('Ratings and comments left for them. Requires the feedback feature on your plan.') }}</span>
            </span>
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            @if ($selfServiceSaved) <span class="text-sm text-green-700">{{ __('Saved.') }}</span> @endif
        </div>
    </form>

    @unless ($tenant->isWhiteLabel())
        <p class="text-sm text-slate-500">{{ __('Your plan shows a small "Powered by" mark on public pages. White-label is available on higher plans.') }}</p>
    @endunless
</div>
