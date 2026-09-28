<?php

namespace Tests\Feature\Signage;

use App\Domain\Access\DevicePairingService;
use App\Domain\Access\Models\Device;
use App\Domain\Access\Roles;
use App\Domain\Display\DisplayChanged;
use App\Domain\Display\SignageFeed;
use App\Domain\Display\TickerMessages;
use App\Domain\Organization\Models\Location;
use App\Domain\Signage\Models\Playlist;
use App\Domain\Signage\Models\PlaylistSchedule;
use App\Domain\Signage\Models\SignageItem;
use App\Domain\Signage\Models\TickerMessage;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\Admin\Signage\SignageManager;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SignageTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private Device $tv;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
        $p = $this->postJson('/devices/pairings', ['type' => 'display'])->json();
        $this->tv = $this->inTenant($this->tenant, fn () => app(DevicePairingService::class)->claim($p['code'], $this->location, 'TV'));
        $this->token = $this->postJson('/devices/pairings/status', $p)->json('token');
        $this->actingAsTenant($this->tenant);
        $this->travelTo(CarbonImmutable::parse('2026-12-22 10:00', 'UTC')); // Tuesday
    }

    private function playlist(string $name, array $titles): Playlist
    {
        $playlist = Playlist::create(['name' => $name]);
        foreach ($titles as $i => $title) {
            $item = SignageItem::create(['type' => 'announcement', 'title' => $title, 'body' => 'x', 'duration_seconds' => 8]);
            $playlist->items()->attach($item->id, ['position' => $i + 1]);
        }

        return $playlist;
    }

    private function playing(): ?string
    {
        return app(SignageFeed::class)->manifest($this->tv->fresh()->load('location'))['playlist']['name'] ?? null;
    }

    public function test_default_playlist_and_seasonal_schedule_precedence(): void
    {
        $default = $this->playlist('Default', ['Welcome']);
        $holiday = $this->playlist('Holiday hours', ['Closed Dec 25']);
        PlaylistSchedule::create(['playlist_id' => $default->id, 'is_default' => true]);
        PlaylistSchedule::create(['playlist_id' => $holiday->id, 'starts_on' => '2026-12-20', 'ends_on' => '2026-12-31']);

        $this->assertSame('Holiday hours', $this->playing());
        $this->travelTo(CarbonImmutable::parse('2027-01-02 10:00', 'UTC'));
        $this->assertSame('Default', $this->playing());
    }

    public function test_display_schedule_beats_location_beats_company(): void
    {
        PlaylistSchedule::create(['playlist_id' => $this->playlist('Company', ['a'])->id]);
        $this->assertSame('Company', $this->playing());

        PlaylistSchedule::create(['playlist_id' => $this->playlist('Location', ['b'])->id, 'location_id' => $this->location->id]);
        $this->assertSame('Location', $this->playing());

        PlaylistSchedule::create(['playlist_id' => $this->playlist('This TV', ['c'])->id, 'device_id' => $this->tv->id]);
        $this->assertSame('This TV', $this->playing());

        $other = Location::factory()->create();
        PlaylistSchedule::create(['playlist_id' => $this->playlist('Elsewhere', ['d'])->id, 'location_id' => $other->id, 'weekdays' => [2]]);
        $this->assertSame('This TV', $this->playing());
    }

    public function test_weekday_and_time_windows_in_location_time(): void
    {
        PlaylistSchedule::create(['playlist_id' => $this->playlist('Default', ['a'])->id, 'is_default' => true]);
        PlaylistSchedule::create(['playlist_id' => $this->playlist('Morning Tue', ['b'])->id, 'weekdays' => [2], 'start_time' => '08:00', 'end_time' => '12:00']);

        $this->assertSame('Morning Tue', $this->playing());
        $this->travelTo(CarbonImmutable::parse('2026-12-22 13:00', 'UTC'));
        $this->assertSame('Default', $this->playing());
        $this->travelTo(CarbonImmutable::parse('2026-12-23 09:00', 'UTC'));
        $this->assertSame('Default', $this->playing());
    }

    public function test_expired_items_are_skipped_and_hash_changes(): void
    {
        $playlist = $this->playlist('P', ['Evergreen']);
        $promo = SignageItem::create(['type' => 'announcement', 'title' => 'Summer promo', 'body' => 'x', 'ends_on' => '2026-12-22', 'duration_seconds' => 5]);
        $playlist->items()->attach($promo->id, ['position' => 2]);
        PlaylistSchedule::create(['playlist_id' => $playlist->id]);

        $feed = app(SignageFeed::class);
        $today = $feed->manifest($this->tv->fresh()->load('location'));
        $this->assertSame(['Evergreen', 'Summer promo'], array_column($today['items'], 'title'));

        $this->travelTo(CarbonImmutable::parse('2026-12-23 10:00', 'UTC'));
        $tomorrow = $feed->manifest($this->tv->fresh()->load('location'));
        $this->assertSame(['Evergreen'], array_column($tomorrow['items'], 'title'));
        $this->assertNotSame($today['hash'], $tomorrow['hash']);
    }

    public function test_rich_text_is_sanitized_qr_is_generated_and_service_info_lists_services(): void
    {
        $playlist = Playlist::create(['name' => 'P']);
        foreach ([
            ['type' => 'rich_text', 'title' => 'Rates', 'body' => "# Rates\n\n**2%** [x](javascript:alert(1))\n\n<script>alert(1)</script>"],
            ['type' => 'qr', 'title' => 'Follow us', 'body' => 'Scan me', 'url' => 'https://example.com'],
            ['type' => 'service_info', 'title' => 'Our services'],
        ] as $i => $attrs) {
            $playlist->items()->attach(SignageItem::create($attrs + ['duration_seconds' => 8])->id, ['position' => $i]);
        }
        PlaylistSchedule::create(['playlist_id' => $playlist->id]);

        $items = app(SignageFeed::class)->manifest($this->tv->fresh()->load('location'))['items'];
        $this->assertStringContainsString('<strong>2%</strong>', $items[0]['html']);
        $this->assertStringNotContainsString('<script', $items[0]['html']);
        $this->assertStringNotContainsString('javascript:', $items[0]['html']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $items[1]['qr']);
        $this->assertSame('General help', $items[2]['services'][0]['name']);
    }

    public function test_manifest_endpoint_requires_display_device(): void
    {
        PlaylistSchedule::create(['playlist_id' => $this->playlist('P', ['a'])->id]);
        $this->tenantContext()->clear();

        $this->withToken($this->token)->getJson('/display/signage')->assertOk()->assertJsonPath('playlist.name', 'P');
        $this->flushHeaders()->getJson('/display/signage')->assertUnauthorized();
    }

    public function test_ticker_for_location_and_company(): void
    {
        $other = Location::factory()->create();
        TickerMessage::create(['body' => 'Everywhere']);
        TickerMessage::create(['body' => 'Here only', 'location_id' => $this->location->id]);
        TickerMessage::create(['body' => 'Other only', 'location_id' => $other->id]);
        TickerMessage::create(['body' => 'Expired', 'ends_on' => '2026-12-01']);

        $this->assertSame(['Everywhere', 'Here only'], app(TickerMessages::class)->for($this->location));
    }

    public function test_publishing_pings_displays(): void
    {
        Event::fake([DisplayChanged::class]);

        TickerMessage::create(['body' => 'New!']);

        Event::assertDispatched(DisplayChanged::class, fn (DisplayChanged $e) => $e->reason === 'signage'
            && in_array('display.'.$this->tv->fresh()->channel_key, array_column($e->broadcastOn(), 'name'), true));
    }

    public function test_upload_validation_and_admin_flow(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->userIn($this->tenant);
        $admin->assignRole(Roles::COMPANY_ADMIN);
        $this->actingAs($admin);

        Livewire::test(SignageManager::class)
            ->set('type', 'image')->set('title', 'Bad')->set('media', UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml'))
            ->call('saveItem')->assertHasErrors('media');

        Livewire::test(SignageManager::class)
            ->set('type', 'image')->set('title', 'Promo')->set('duration_seconds', 12)
            ->set('media', UploadedFile::fake()->image('promo.png'))
            ->call('saveItem')->assertHasNoErrors();
        $item = SignageItem::query()->sole();
        Storage::disk('public')->assertExists($item->media_path);

        $c = Livewire::test(SignageManager::class)->set('tab', 'playlists')
            ->set('playlistName', 'Lobby')->call('createPlaylist')
            ->set('addItemId', $item->id)->call('addToPlaylist')
            ->set('schedule.target', 'location')->set('schedule.location_id', $this->location->id)->call('addSchedule')
            ->assertHasNoErrors();

        $this->assertSame('Lobby', $this->playing());
        $this->assertSame(12, app(SignageFeed::class)->manifest($this->tv->fresh()->load('location'))['items'][0]['duration']);
    }

    public function test_cannot_touch_another_tenants_playlist_items(): void
    {
        [, $b] = [$this->tenant, Tenant::query()->where('id', '!=', $this->tenant->id)->first()];
        $theirs = $this->inTenant($b, fn () => $this->playlist('Theirs', ['secret']));
        $pivotId = \DB::table('playlist_items')->where('playlist_id', $theirs->id)->value('id');

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->userIn($this->tenant);
        $admin->assignRole(Roles::COMPANY_ADMIN);
        $this->actingAs($admin);

        try {
            Livewire::test(SignageManager::class)->set('playlistId', $theirs->id)->call('removeFromPlaylist', $pivotId);
        } catch (ModelNotFoundException) {
        }

        $this->assertSame(1, \DB::table('playlist_items')->where('playlist_id', $theirs->id)->count());
    }
}
