<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Location;
use App\Livewire\Admin\Concerns\AuthorizesLocations;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Per-location setup page; each tab is its own child component. */
#[Layout('layouts.app')]
class LocationSetup extends Component
{
    use AuthorizesLocations;

    public const TABS = ['departments', 'desks', 'services', 'routing', 'hours', 'closures', 'qr'];

    public Location $location;

    #[Url]
    public string $tab = 'departments';

    public function mount(Location $location): void
    {
        $this->authorizeLocation($location);
        $this->location = $location;
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'departments';
        }
    }

    public function render()
    {
        return view('livewire.admin.setup.location-setup');
    }
}
