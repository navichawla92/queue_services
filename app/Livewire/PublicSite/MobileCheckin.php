<?php

namespace App\Livewire\PublicSite;

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\OperatingHours;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\Models\Ticket;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;

/** QR / mobile check-in at /c/{location} (location from its public id). */
#[Layout('layouts.public')]
class MobileCheckin extends CheckinFlow
{
    #[Locked]
    public int $locationId;

    #[Locked]
    public string $source = 'mobile';

    /** @param  string  $location  the location's public check-in id (tenant bound by middleware) */
    public function mount(string $location): void
    {
        $location = Location::query()->where('checkin_public_id', $location)->firstOrFail();
        $this->locationId = $location->id;
        $this->source = request()->query('src') === 'qr' ? 'qr' : 'mobile';
        $this->locale = in_array($l = substr((string) request()->getPreferredLanguage($this->languages()), 0, 2), $this->languages(), true) ? $l : 'en';
        $this->bootCheckinFlow();

        // Say so up front when walk-ins are not accepted right now.
        if (! app(OperatingHours::class)->acceptsWalkIns($location, now())) {
            $this->step = 'closed';
        }
    }

    protected function location(): Location
    {
        return Location::query()->findOrFail($this->locationId);
    }

    protected function channel(): CheckinChannel
    {
        return $this->source === 'qr' ? CheckinChannel::Qr : CheckinChannel::Mobile;
    }

    protected function actor(): Actor
    {
        return Actor::customer();
    }

    /** Mobile customers continue on their live status page. */
    protected function afterCheckin(Ticket $ticket): void
    {
        $this->redirectRoute('public.ticket', ['ticket' => $ticket->public_token]);
    }

    public function render()
    {
        return view('livewire.public-site.mobile-checkin', $this->viewData())
            ->title($this->location()->name);
    }
}
