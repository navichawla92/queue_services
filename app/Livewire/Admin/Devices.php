<?php

namespace App\Livewire\Admin;

use App\Domain\Access\DevicePairingService;
use App\Domain\Access\LocationAccess;
use App\Domain\Access\Models\Device;
use App\Domain\Display\DisplayChanged;
use App\Domain\Display\DisplayConfig;
use App\Domain\Display\DisplayNotifier;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    // ---- Display configuration (lobby-display "Screen layouts", panels) ----

    public ?int $configuringId = null;

    /** @var array<string, mixed> */
    public array $config = [];

    public bool $showNames = false;

    public function configure(int $deviceId, LocationAccess $access): void
    {
        $device = $this->manageableDisplay($deviceId, $access);
        $this->configuringId = $device->id;
        $this->config = DisplayConfig::for($device);
    }

    public function saveConfig(LocationAccess $access, DisplayNotifier $notifier): void
    {
        $device = $this->manageableDisplay((int) $this->configuringId, $access);
        $departmentIds = Department::query()->where('location_id', $device->location_id)->pluck('id')->all();

        $this->validate([
            'config.layout' => ['required', Rule::in(DisplayConfig::LAYOUTS)],
            'config.orientation' => ['required', Rule::in(DisplayConfig::ORIENTATIONS)],
            'config.queue_zone' => ['required', Rule::in(['left', 'right'])],
            'config.department_ids' => ['array'],
            'config.department_ids.*' => [Rule::in($departmentIds)],
            'config.waiting_rows' => ['required', 'integer', 'between:0,30'],
            'config.highlight_seconds' => ['required', 'integer', 'between:3,60'],
            'config.show_employee_name' => ['boolean'],
            'config.show_avg_wait' => ['boolean'],
            'config.chime' => ['boolean'],
            'config.header' => ['boolean'],
            'config.ticker' => ['boolean'],
        ]);

        $device->update(['config' => DisplayConfig::normalize($this->config)]);
        $notifier->device($device, DisplayChanged::CONFIG);
        $this->configuringId = null;
    }

    /** Company-wide privacy opt-in: "First L." on lobby displays. */
    public function updatedShowNames(): void
    {
        Gate::authorize('tenant.manage');
        $tenant = app(TenantContext::class)->require();
        $tenant->forceFill(['settings' => array_merge($tenant->settings ?? [], ['show_customer_names_on_display' => $this->showNames])])->save();
    }

    private function manageableDisplay(int $deviceId, LocationAccess $access): Device
    {
        $device = Device::query()->with('location')->where('type', Device::TYPE_DISPLAY)->findOrFail($deviceId);
        abort_unless($access->allows(auth()->user(), 'displays.manage', $device->location), 403);

        return $device;
    }

    public function render(LocationAccess $access)
    {
        Gate::authorize('displays.manage');
        $locations = $access->accessibleLocations(auth()->user())->get();
        $this->showNames = (bool) app(TenantContext::class)->require()->settings()->get('show_customer_names_on_display', false);
        $configuring = $this->configuringId ? Device::query()->find($this->configuringId) : null;

        return view('livewire.admin.devices', [
            'locations' => $locations,
            'devices' => Device::query()->with('location')
                ->whereIn('location_id', $locations->pluck('id'))
                ->orderBy('location_id')->orderBy('name')->get(),
            'configuring' => $configuring,
            'configDepartments' => $configuring ? Department::query()->where('location_id', $configuring->location_id)->ordered()->get() : collect(),
        ]);
    }
}
