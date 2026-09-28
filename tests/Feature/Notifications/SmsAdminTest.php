<?php

namespace Tests\Feature\Notifications;

use App\Domain\Access\Roles;
use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\SmsSetting;
use App\Domain\Notifications\Models\SmsTemplate;
use App\Domain\Organization\Models\Location;
use App\Livewire\Admin\SmsSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SmsAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $admin = $this->userIn($a);
        $admin->assignRole(Roles::COMPANY_ADMIN);
        $this->actingAs($admin);
    }

    public function test_template_with_unknown_placeholder_is_rejected_and_preview_counts_segments(): void
    {
        Livewire::test(SmsSettings::class)
            ->set('templateEvent', 'representative_ready')
            ->set('templateBody', 'Hi {{first_name}}, your {{favourite_color}} is ready')
            ->call('saveTemplate')
            ->assertHasErrors('templateBody')
            ->assertSee('favourite_color');

        Livewire::test(SmsSettings::class)
            ->set('templateEvent', 'representative_ready')
            ->set('templateBody', 'Hi {{first_name}}, go to {{desk}}.')
            ->assertSee('Hi Jane, go to Desk 3.')
            ->assertSee('1 segment')
            ->call('saveTemplate')->assertHasNoErrors();

        $this->assertSame('Hi {{first_name}}, go to {{desk}}.', SmsTemplate::query()->sole()->body);
    }

    public function test_provider_credentials_are_stored_encrypted_and_never_echoed(): void
    {
        Livewire::test(SmsSettings::class)
            ->set('provider', 'twilio')->set('account_sid', 'AC123')->set('auth_token', 'super-secret')
            ->set('from_number', '+12025550100')
            ->call('saveProvider')->assertHasNoErrors()
            ->assertDontSee('super-secret');

        $settings = SmsSetting::query()->sole();
        $this->assertSame('super-secret', $settings->auth_token);
        $raw = \DB::table('sms_settings')->value('auth_token');
        $this->assertStringNotContainsString('super-secret', (string) $raw);
    }

    public function test_toggles_save_company_and_location_override(): void
    {
        $location = Location::factory()->create();

        Livewire::test(SmsSettings::class)
            ->set('enabled.position_update', false)
            ->set("overrides.{$location->id}.position_update", '1')
            ->call('saveToggles');

        $this->assertFalse(NotificationSetting::query()->whereNull('location_id')->where('event', 'position_update')->value('enabled'));
        $this->assertTrue((bool) NotificationSetting::query()->where('location_id', $location->id)->where('event', 'position_update')->value('enabled'));
    }
}
