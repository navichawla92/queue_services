<?php

namespace App\Livewire\Admin;

use App\Domain\Access\DevicePairingService;
use App\Domain\Access\LocationAccess;
use App\Domain\Access\Models\Device;
use App\Domain\Organization\Models\Location;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/** Pair and revoke kiosks & lobby displays (permission: displays.manage). */
#[Layout('layouts.app')]
class Devices extends Component
{
    #[Validate('required|string|size:6')]
    public string $code = '';

    #[Validate('required|integer')]
    public ?int $location_id = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    public ?string $pairedMessage = null;

    public function mount(LocationAccess $access): void
    {
        $this->location_id = $access->accessibleLocations(auth()->user())->value('id');
    }

    public function pair(DevicePairingService $pairing, LocationAccess $access): void
    {
        $this->validate();

        $location = Location::query()->findOrFail($this->location_id);
        abort_unless($access->allows(auth()->user(), 'displays.manage', $location), 403);

        $device = $pairing->claim($this->code, $location, $this->name);

        $this->pairedMessage = __(':name paired to :location.', ['name' => $device->name, 'location' => $location->name]);
        $this->reset('code', 'name');
    }

    public function revoke(int $deviceId, DevicePairingService $pairing, LocationAccess $access): void
    {
        $device = Device::query()->with('location')->findOrFail($deviceId);
        abort_unless($access->allows(auth()->user(), 'displays.manage', $device->location), 403);

        $pairing->revoke($device);
    }

    public function render(LocationAccess $access)
    {
        Gate::authorize('displays.manage');
        $locations = $access->accessibleLocations(auth()->user())->get();

        return view('livewire.admin.devices', [
            'locations' => $locations,
            'devices' => Device::query()->with('location')
                ->whereIn('location_id', $locations->pluck('id'))
                ->orderBy('location_id')->orderBy('name')->get(),
        ]);
    }
}
