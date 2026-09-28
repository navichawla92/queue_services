<?php

namespace App\Livewire\Admin;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BrandingSettings extends Component
{
    use WithFileUploads;

    #[Validate('nullable|string|max:100')]
    public ?string $display_name = null;

    #[Validate(['required', 'regex:/^#[0-9a-fA-F]{6}$/'])]
    public string $primary_color = '#1d4ed8';

    #[Validate(['required', 'regex:/^#[0-9a-fA-F]{6}$/'])]
    public string $accent_color = '#f59e0b';

    #[Validate('nullable|string|max:500')]
    public ?string $public_text = null;

    /** SVG deliberately excluded (can carry script). */
    #[Validate('nullable|image|mimes:png,jpg,jpeg,webp|max:2048')]
    public ?TemporaryUploadedFile $logo = null;

    public bool $saved = false;

    public function mount(TenantContext $context): void
    {
        $tenant = $context->require();
        $this->display_name = $tenant->display_name;
        $this->primary_color = $tenant->primary_color;
        $this->accent_color = $tenant->accent_color;
        $this->public_text = $tenant->public_text;
    }

    public function save(TenantContext $context): void
    {
        $this->validate();
        $tenant = $context->require();
        $disk = Storage::disk(config('filesystems.media'));

        if ($this->logo) {
            $path = $this->logo->store("tenants/{$tenant->id}/branding", config('filesystems.media'));
            if ($tenant->logo_path) {
                $disk->delete($tenant->logo_path);
            }
            $tenant->logo_path = $path;
            $this->logo = null;
        }

        $tenant->fill([
            'display_name' => $this->display_name ?: null,
            'primary_color' => strtolower($this->primary_color),
            'accent_color' => strtolower($this->accent_color),
            'public_text' => $this->public_text ?: null,
        ])->save();

        $this->saved = true;
    }

    public function removeLogo(TenantContext $context): void
    {
        $tenant = $context->require();
        if ($tenant->logo_path) {
            Storage::disk(config('filesystems.media'))->delete($tenant->logo_path);
            $tenant->forceFill(['logo_path' => null])->save();
        }
    }

    public function render()
    {
        return view('livewire.admin.branding-settings', [
            'tenant' => app(TenantContext::class)->require(),
        ]);
    }
}
