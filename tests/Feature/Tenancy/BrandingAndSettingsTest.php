<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Models\Plan;
use App\Livewire\Admin\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BrandingAndSettingsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_admin_can_save_branding_with_logo(): void
    {
        Storage::fake('public');
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);

        Livewire::test(BrandingSettings::class)
            ->set('display_name', 'Acme Bank')
            ->set('primary_color', '#112233')
            ->set('accent_color', '#AABBCC')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $a->refresh();
        $this->assertSame('Acme Bank', $a->brandName());
        $this->assertSame('#112233', $a->primary_color);
        $this->assertSame('#aabbcc', $a->accent_color);
        Storage::disk('public')->assertExists($a->logo_path);
        $this->assertStringStartsWith("tenants/{$a->id}/branding/", $a->logo_path);
    }

    public function test_invalid_color_and_svg_logo_are_rejected(): void
    {
        Storage::fake('public');
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);

        Livewire::test(BrandingSettings::class)
            ->set('primary_color', 'red;}body{display:none')
            ->set('logo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors(['primary_color', 'logo']);
    }

    public function test_public_layout_applies_branding_and_powered_by_depends_on_plan(): void
    {
        $basic = Plan::factory()->withFeatures(['white_label' => false])->create();
        $premium = Plan::factory()->withFeatures(['white_label' => true])->create();
        $a = $this->createTenant(['display_name' => 'Acme', 'primary_color' => '#123456'], $basic);
        $b = $this->createTenant(['display_name' => 'Bolt'], $premium);

        $htmlA = view('public.unavailable', ['tenant' => $a])->render();
        $this->assertStringContainsString('--brand: #123456', $htmlA);
        $this->assertStringContainsString('Acme', $htmlA);
        $this->assertStringContainsString('powered-by', $htmlA);

        $htmlB = view('public.unavailable', ['tenant' => $b])->render();
        $this->assertStringNotContainsString('powered-by', $htmlB);
    }

    public function test_settings_defaults_tenant_values_and_location_timezone_override(): void
    {
        [$a] = $this->twoTenants();
        $a->forceFill(['settings' => ['timezone' => 'America/New_York', 'retention_days' => 365]])->save();

        $settings = $a->fresh()->settings();
        $this->assertSame('America/New_York', $settings->timezone());
        $this->assertSame(365, $settings->get('retention_days'));
        $this->assertSame('en', $settings->get('locale'));

        $location = (object) ['timezone' => 'America/Chicago'];
        $this->assertSame('America/Chicago', $settings->forLocation($location)->timezone());
        $this->assertSame('America/New_York', $settings->forLocation((object) ['timezone' => null])->timezone());
        $this->assertSame(365, $settings->forLocation($location)->get('retention_days'));
    }
}
