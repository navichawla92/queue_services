<?php

namespace App\Domain\Signage;

use App\Domain\Access\Models\Device;
use App\Domain\Display\DisplayChanged;
use App\Domain\Display\DisplayNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Pushes signage changes to the company's displays (digital-signage
 * "Remote publishing"): one ping per request after commit; displays then
 * reload the manifest (they also poll it every 60 s).
 */
class SignagePublisher
{
    private bool $pending = false;

    public function changed(): void
    {
        if ($this->pending) {
            return;
        }
        $this->pending = true;

        DB::afterCommit(function () {
            $this->pending = false;
            Device::query()->where('type', Device::TYPE_DISPLAY)->whereNull('revoked_at')->pluck('location_id')->unique()
                ->each(fn (int $locationId) => app(DisplayNotifier::class)->location($locationId, DisplayChanged::SIGNAGE));
        });
    }
}
