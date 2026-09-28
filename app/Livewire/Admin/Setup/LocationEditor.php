<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Location;
use App\Livewire\Admin\Concerns\AuthorizesLocations;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Base for per-location setup tabs: the location id is locked and every
 * request re-checks setup.manage for it.
 */
abstract class LocationEditor extends Component
{
    use AuthorizesLocations;

    #[Locked]
    public int $locationId;

    /** Subsequent requests: re-authorize after properties are restored. */
    public function hydrate(): void
    {
        $this->authorizeLocation($this->location());
    }

    public function mount(int $locationId): void
    {
        $this->locationId = $locationId;
        $this->authorizeLocation($this->location());
    }

    protected function location(): Location
    {
        return Location::query()->findOrFail($this->locationId);
    }
}
