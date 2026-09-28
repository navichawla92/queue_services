<div class="max-w-2xl space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Branding') }}</h1>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
        <div>
            <label class="block text-sm font-medium" for="display_name">{{ __('Display name') }}</label>
            <input id="display_name" type="text" wire:model="display_name" placeholder="{{ $tenant->name }}"
                   class="mt-1 w-full rounded border-slate-300">
            @error('display_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium" for="primary_color">{{ __('Primary color') }}</label>
                <input id="primary_color" type="color" wire:model="primary_color" class="mt-1 h-10 w-full">
                @error('primary_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="accent_color">{{ __('Accent color') }}</label>
                <input id="accent_color" type="color" wire:model="accent_color" class="mt-1 h-10 w-full">
                @error('accent_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium" for="public_text">{{ __('Welcome text on public pages') }}</label>
            <textarea id="public_text" wire:model="public_text" rows="3" class="mt-1 w-full rounded border-slate-300"></textarea>
            @error('public_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium" for="logo">{{ __('Logo (PNG, JPG or WebP, max 2 MB)') }}</label>
            @if ($tenant->logo_path)
                <div class="mt-2 flex items-center gap-3">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($tenant->logo_path) }}" class="h-12" alt="">
                    <button type="button" wire:click="removeLogo" class="text-sm text-red-600">{{ __('Remove') }}</button>
                </div>
            @endif
            <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="mt-2">
            @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Save') }}</button>
            @if ($saved) <span class="text-sm text-green-700">{{ __('Saved.') }}</span> @endif
        </div>
    </form>

    @unless ($tenant->isWhiteLabel())
        <p class="text-sm text-slate-500">{{ __('Your plan shows a small "Powered by" mark on public pages. White-label is available on higher plans.') }}</p>
    @endunless
</div>
