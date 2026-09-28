<?php

namespace App\Livewire\PublicSite;

use App\Domain\Access\DeviceContext;
use App\Domain\Access\Models\Device;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use Livewire\Attributes\Layout;

/**
 * Lobby kiosk at /kiosk/app (paired device only). Resets to the welcome
 * screen after 60 s of inactivity and 12 s after showing a ticket.
 */
#[Layout('layouts.kiosk')]
class KioskCheckin extends CheckinFlow
{
    public const IDLE_RESET_SECONDS = 60;

    public const CONFIRMATION_SECONDS = 12;

    public bool $largeText = false;

    public bool $highContrast = false;

    protected function device(): Device
    {
        return app(DeviceContext::class)->require();
    }

    protected function location(): Location
    {
        return $this->device()->location;
    }

    protected function channel(): CheckinChannel
    {
        return CheckinChannel::Kiosk;
    }

    protected function actor(): Actor
    {
        return Actor::device($this->device());
    }

    public function toggleLargeText(): void
    {
        $this->largeText = ! $this->largeText;
    }

    public function toggleContrast(): void
    {
        $this->highContrast = ! $this->highContrast;
    }

    /** Called by the idle timer; clears everything a customer entered. */
    public function idleReset(): void
    {
        $this->startOver();
        $this->largeText = false;
        $this->highContrast = false;
        $this->setLocale('en');
    }

    public function render()
    {
        return view('livewire.public-site.kiosk-checkin', $this->viewData() + [
            'idleSeconds' => self::IDLE_RESET_SECONDS,
            'confirmationSeconds' => self::CONFIRMATION_SECONDS,
        ])->title($this->location()->name);
    }
}
