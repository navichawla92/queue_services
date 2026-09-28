<?php

namespace App\Livewire\Admin\Signage;

use App\Domain\Access\LocationAccess;
use App\Domain\Access\Models\Device;
use App\Domain\Organization\Models\Location;
use App\Domain\Signage\Models\Playlist;
use App\Domain\Signage\Models\PlaylistSchedule;
use App\Domain\Signage\Models\SignageItem;
use App\Domain\Signage\Models\TickerMessage;
use App\Domain\Signage\SignagePublisher;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Digital signage admin (permission: signage.manage): content library,
 * playlists with ordering and schedules, and ticker messages. Location
 * managers may only target their own locations and displays.
 */
#[Layout('layouts.app')]
class SignageManager extends Component
{
    use WithFileUploads;

    #[Url]
    public string $tab = 'content';

    // ---- content form
    public ?int $itemId = null;

    public string $type = 'announcement';

    public string $title = '';

    public ?string $body = null;

    public ?string $url = null;

    public ?int $duration_seconds = 10;

    public ?string $starts_on = null;

    public ?string $ends_on = null;

    public ?TemporaryUploadedFile $media = null;

    // ---- playlists
    public ?int $playlistId = null;

    public string $playlistName = '';

    public ?int $addItemId = null;

    /** @var array<string, mixed> */
    public array $schedule = ['target' => 'company', 'location_id' => null, 'device_id' => null, 'is_default' => false,
        'starts_on' => null, 'ends_on' => null, 'weekdays' => [], 'start_time' => null, 'end_time' => null];

    // ---- ticker
    public string $tickerBody = '';

    public ?int $tickerLocationId = null;

    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('signage.manage');
    }

    // =============================================================== content

    public function editItem(int $id): void
    {
        $i = SignageItem::query()->findOrFail($id);
        $this->itemId = $i->id;
        $this->fill([
            'type' => $i->type, 'title' => $i->title, 'body' => $i->body, 'url' => $i->url,
            'duration_seconds' => $i->duration_seconds,
            'starts_on' => $i->starts_on?->format('Y-m-d'), 'ends_on' => $i->ends_on?->format('Y-m-d'),
        ]);
    }

    public function saveItem(): void
    {
        Gate::authorize('signage.manage');
        $maxKb = $this->maxUploadKb();
        $needsMedia = in_array($this->type, ['image', 'video'], true) && ! $this->itemId;

        $data = $this->validate([
            'type' => ['required', Rule::in(SignageItem::TYPES)],
            'title' => ['required', 'string', 'max:150'],
            'body' => [Rule::requiredIf(in_array($this->type, ['announcement', 'rich_text'], true)), 'nullable', 'string', 'max:5000'],
            'url' => [Rule::requiredIf($this->type === 'qr'), 'nullable', 'url:https,http', 'max:2048'],
            'duration_seconds' => [$this->type === 'video' ? 'nullable' : 'required', 'nullable', 'integer', 'between:3,600'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'media' => [
                $needsMedia ? 'required' : 'nullable',
                'file',
                "max:{$maxKb}",
                match ($this->type) {
                    'image' => 'mimetypes:image/jpeg,image/png,image/webp',
                    'video' => 'mimetypes:video/mp4',
                    default => 'prohibited',
                },
            ],
        ], ['media.mimetypes' => __('Images must be JPG, PNG or WebP; videos MP4 (H.264).')]);
        unset($data['media']);

        $item = $this->itemId ? SignageItem::query()->findOrFail($this->itemId) : new SignageItem;
        if ($this->media) {
            $tenantId = app(TenantContext::class)->id();
            if ($item->media_path) {
                Storage::disk(config('filesystems.media'))->delete($item->media_path);
            }
            $item->media_path = $this->media->store("tenants/{$tenantId}/signage", config('filesystems.media'));
            $item->media_mime = $this->media->getMimeType();
            $item->media_size = $this->media->getSize();
        }
        $item->fill($data + ['duration_seconds' => $this->type === 'video' ? null : $this->duration_seconds])->save();

        $this->flash = __('Content saved.');
        $this->resetItemForm();
    }

    public function toggleItem(int $id): void
    {
        $i = SignageItem::query()->findOrFail($id);
        $i->update(['is_active' => ! $i->is_active]);
    }

    public function deleteItem(int $id): void
    {
        $i = SignageItem::query()->findOrFail($id);
        if ($i->media_path) {
            Storage::disk(config('filesystems.media'))->delete($i->media_path);
        }
        $i->delete();
    }

    public function resetItemForm(): void
    {
        $this->reset('itemId', 'title', 'body', 'url', 'starts_on', 'ends_on', 'media');
        $this->duration_seconds = 10;
        $this->resetValidation();
    }

    private function maxUploadKb(): int
    {
        $mb = app(TenantContext::class)->require()->plan?->limit('media_upload_mb') ?? 100;

        return $mb * 1024;
    }

    // ============================================================= playlists

    public function createPlaylist(): void
    {
        $this->validate(['playlistName' => ['required', 'string', 'max:100']]);
        $this->playlistId = Playlist::create(['name' => $this->playlistName])->id;
        $this->playlistName = '';
    }

    public function selectPlaylist(int $id): void
    {
        $this->playlistId = Playlist::query()->findOrFail($id)->id;
    }

    public function addToPlaylist(): void
    {
        $playlist = Playlist::query()->findOrFail($this->playlistId);
        $item = SignageItem::query()->findOrFail($this->addItemId);
        $next = (int) DB::table('playlist_items')->where('playlist_id', $playlist->id)->max('position') + 1;
        $playlist->items()->attach($item->id, ['position' => $next]);
        $this->addItemId = null;
        app(SignagePublisher::class)->changed();
    }

    /** playlist_items has no tenant column: always resolve the playlist through the tenant scope first. */
    private function ownPlaylistId(): int
    {
        return Playlist::query()->findOrFail($this->playlistId)->id;
    }

    public function removeFromPlaylist(int $pivotId): void
    {
        DB::table('playlist_items')->where('playlist_id', $this->ownPlaylistId())->where('id', $pivotId)->delete();
        $this->renumber();
    }

    public function move(int $pivotId, int $direction): void
    {
        $rows = DB::table('playlist_items')->where('playlist_id', $this->ownPlaylistId())->orderBy('position')->pluck('id')->all();
        $index = array_search($pivotId, $rows, true);
        $swap = $index === false ? null : $index + $direction;
        if ($swap === null || ! isset($rows[$swap])) {
            return;
        }
        [$rows[$index], $rows[$swap]] = [$rows[$swap], $rows[$index]];
        foreach ($rows as $pos => $id) {
            DB::table('playlist_items')->where('id', $id)->update(['position' => $pos + 1]);
        }
        app(SignagePublisher::class)->changed();
    }

    private function renumber(): void
    {
        $rows = DB::table('playlist_items')->where('playlist_id', $this->ownPlaylistId())->orderBy('position')->pluck('id');
        foreach ($rows as $pos => $id) {
            DB::table('playlist_items')->where('id', $id)->update(['position' => $pos + 1]);
        }
        app(SignagePublisher::class)->changed();
    }

    public function deletePlaylist(int $id): void
    {
        Playlist::query()->findOrFail($id)->delete();
        $this->playlistId = null;
    }

    public function addSchedule(LocationAccess $access): void
    {
        $playlist = Playlist::query()->findOrFail($this->playlistId);
        $locationIds = $access->accessibleLocations(auth()->user())->pluck('id')->all();
        $allowCompany = $access->coversAllLocations(auth()->user());

        $this->validate([
            'schedule.target' => ['required', Rule::in($allowCompany ? ['company', 'location', 'device'] : ['location', 'device'])],
            'schedule.location_id' => ['nullable', Rule::requiredIf($this->schedule['target'] === 'location'), Rule::in($locationIds)],
            'schedule.device_id' => ['nullable', Rule::requiredIf($this->schedule['target'] === 'device'),
                Rule::in(Device::query()->where('type', Device::TYPE_DISPLAY)->whereIn('location_id', $locationIds)->pluck('id')->all())],
            'schedule.is_default' => ['boolean'],
            'schedule.starts_on' => ['nullable', 'date'],
            'schedule.ends_on' => ['nullable', 'date', 'after_or_equal:schedule.starts_on'],
            'schedule.weekdays' => ['array'],
            'schedule.weekdays.*' => ['integer', 'between:0,6'],
            'schedule.start_time' => ['nullable', 'date_format:H:i'],
            'schedule.end_time' => ['nullable', 'date_format:H:i', 'required_with:schedule.start_time', 'after:schedule.start_time'],
        ], [], ['schedule.location_id' => __('location'), 'schedule.device_id' => __('display')]);

        $s = $this->schedule;
        $playlist->schedules()->create([
            'location_id' => $s['target'] === 'location' ? $s['location_id'] : null,
            'device_id' => $s['target'] === 'device' ? $s['device_id'] : null,
            'is_default' => (bool) $s['is_default'],
            'starts_on' => $s['starts_on'] ?: null,
            'ends_on' => $s['ends_on'] ?: null,
            'weekdays' => $s['weekdays'] ? array_values(array_map('intval', $s['weekdays'])) : null,
            'start_time' => $s['start_time'] ?: null,
            'end_time' => $s['end_time'] ?: null,
        ]);
        $this->reset('schedule');
    }

    public function deleteSchedule(int $id): void
    {
        PlaylistSchedule::query()->where('playlist_id', $this->ownPlaylistId())->findOrFail($id)->delete();
    }

    // ================================================================ ticker

    public function addTicker(LocationAccess $access): void
    {
        $this->validate([
            'tickerBody' => ['required', 'string', 'max:280'],
            'tickerLocationId' => [$access->coversAllLocations(auth()->user()) ? 'nullable' : 'required',
                Rule::in($access->accessibleLocations(auth()->user())->pluck('id')->all())],
        ]);
        TickerMessage::create(['body' => $this->tickerBody, 'location_id' => $this->tickerLocationId]);
        $this->reset('tickerBody', 'tickerLocationId');
    }

    public function deleteTicker(int $id, LocationAccess $access): void
    {
        $ticker = TickerMessage::query()->findOrFail($id);
        if (! $access->coversAllLocations(auth()->user())) {
            abort_unless($ticker->location_id && $access->accessibleLocations(auth()->user())->whereKey($ticker->location_id)->exists(), 403);
        }
        $ticker->delete();
    }

    public function render(LocationAccess $access)
    {
        Gate::authorize('signage.manage');
        $locations = $access->accessibleLocations(auth()->user())->get();

        return view('livewire.admin.signage.manager', [
            'items' => SignageItem::query()->latest('id')->get(),
            'playlists' => Playlist::query()->withCount('items')->orderBy('name')->get(),
            'playlist' => $this->playlistId ? Playlist::query()->with(['items', 'schedules'])->find($this->playlistId) : null,
            'locations' => $locations,
            'displays' => Device::query()->where('type', Device::TYPE_DISPLAY)->whereNull('revoked_at')->whereIn('location_id', $locations->pluck('id'))->get(),
            'tickers' => TickerMessage::query()->orderBy('sort_order')->orderBy('id')->get(),
            'allowCompany' => $access->coversAllLocations(auth()->user()),
            'maxMb' => intdiv($this->maxUploadKb(), 1024),
            'days' => [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 0 => __('Sun')],
            'locationNames' => $locations->pluck('name', 'id'),
        ]);
    }
}
