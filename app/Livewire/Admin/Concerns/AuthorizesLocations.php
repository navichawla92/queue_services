<?php

namespace App\Livewire\Admin\Concerns;

use App\Domain\Access\LocationAccess;
use App\Domain\Organization\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Location-scoped permission checks for admin Livewire components. */
trait AuthorizesLocations
{
    protected function authorizeLocation(Location $location, string $permission = 'setup.manage'): void
    {
        abort_unless(app(LocationAccess::class)->allows($this->actor(), $permission, $location), 403);
    }

    /** @return Collection<int, Location> */
    protected function accessibleLocations(): Collection
    {
        return app(LocationAccess::class)->accessibleLocations($this->actor())->get();
    }

    protected function coversAllLocations(): bool
    {
        return app(LocationAccess::class)->coversAllLocations($this->actor());
    }

    protected function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
