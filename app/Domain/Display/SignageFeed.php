<?php

namespace App\Domain\Display;

use App\Domain\Access\Models\Device;
use App\Domain\Billing\Features;
use App\Domain\Organization\Models\Service;
use App\Domain\Signage\Models\PlaylistSchedule;
use App\Domain\Signage\Models\SignageItem;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves what a display plays now (digital-signage "Scheduling"): the most
 * specific active schedule for the device → its location → the company,
 * else the most specific default. Items outside their dates are skipped.
 */
class SignageFeed
{
    /** @return array{hash: string|null, playlist: array{id: int, name: string}|null, items: list<array<string, mixed>>} */
    public function manifest(Device $device): array
    {
        $location = $device->location;
        $local = CarbonImmutable::now($location->effectiveTimezone());
        $schedule = app(Features::class)->enabled('signage') ? $this->currentSchedule($device, $local) : null;

        if ($schedule === null) {
            return ['hash' => null, 'playlist' => null, 'items' => []];
        }

        $disk = Storage::disk(config('filesystems.media'));
        $date = $local->format('Y-m-d');

        $items = [];
        /** @var SignageItem $i */
        foreach ($schedule->playlist->items as $i) {
            if (! $i->isLiveOn($date)) {
                continue;
            }
            $items[] = array_filter([
                'id' => $i->id,
                'key' => $i->getRelation('pivot')->getAttribute('id'),
                'type' => $i->type,
                'title' => $i->title,
                'text' => in_array($i->type, ['announcement', 'qr'], true) ? $i->body : null,
                'html' => $i->type === 'rich_text' ? $this->markdown((string) $i->body) : null,
                'media' => $i->media_path ? $disk->url($i->media_path) : null,
                'mime' => $i->media_mime,
                'qr' => $i->type === 'qr' && $i->url ? $this->qr($i->url) : null,
                'services' => $i->type === 'service_info'
                    ? Service::query()->offeredAt($location, customerFacing: true)->get(['id', 'name', 'description', 'expected_minutes'])
                        ->map(fn ($s) => ['name' => $s->name, 'description' => $s->description])->values()->all()
                    : null,
                'duration' => $i->type === 'video' ? null : ($i->duration_seconds ?: SignageItem::DEFAULT_DURATION),
                'updated' => $i->updated_at?->getTimestamp(),
            ], fn ($v) => $v !== null);
        }

        $playlist = ['id' => $schedule->playlist->id, 'name' => $schedule->playlist->name];

        return [
            'hash' => sha1(json_encode([$playlist, $items]) ?: ''),
            'playlist' => $playlist,
            'items' => $items,
        ];
    }

    public function hash(Device $device): ?string
    {
        return $this->manifest($device)['hash'];
    }

    private function currentSchedule(Device $device, CarbonImmutable $local): ?PlaylistSchedule
    {
        $candidates = PlaylistSchedule::query()->with('playlist.items')
            ->where(fn ($q) => $q
                ->where('device_id', $device->id)
                ->orWhere(fn ($q) => $q->whereNull('device_id')->where('location_id', $device->location_id))
                ->orWhere(fn ($q) => $q->whereNull('device_id')->whereNull('location_id')))
            ->get()
            ->filter(fn (PlaylistSchedule $s) => $s->isActiveAt($local));

        $pick = fn ($set) => $set->sortBy([fn ($a, $b) => $b->specificity() <=> $a->specificity(), fn ($a, $b) => $b->id <=> $a->id])->first();

        return $pick($candidates->where('is_default', false)) ?? $pick($candidates->where('is_default', true));
    }

    private function qr(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(600, 1), new SvgImageBackEnd)))->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Safe Markdown → HTML (raw HTML stripped, unsafe links dropped). */
    private function markdown(string $text): string
    {
        return (string) Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
