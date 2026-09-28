<?php

namespace Tests\Feature\Organization;

use App\Domain\Organization\Models\Closure;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\OpeningHour;
use App\Domain\Organization\OperatingHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OperatingHoursTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Location $location;

    private OperatingHours $hours;

    protected function setUp(): void
    {
        parent::setUp();
        [$a] = $this->twoTenants();
        $a->forceFill(['settings' => ['timezone' => 'America/New_York']])->save();
        $this->actingAsTenant($a->fresh());

        $this->location = Location::factory()->create(['walkin_cutoff_minutes' => 15]);
        // Mon–Fri 09:00–17:00
        foreach (range(1, 5) as $weekday) {
            OpeningHour::create(['location_id' => $this->location->id, 'weekday' => $weekday, 'opens_at' => '09:00', 'closes_at' => '17:00']);
        }
        $this->hours = app(OperatingHours::class);
    }

    /** Local New York time → instant. 2026-10-05 is a Monday. */
    private function ny(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'America/New_York');
    }

    public function test_open_during_hours_and_closed_outside(): void
    {
        $this->assertTrue($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-05 10:00')));
        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-05 08:59')));
        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-04 12:00')), 'Sunday');
    }

    public function test_evaluated_in_location_time_zone_not_utc(): void
    {
        // 14:30 UTC = 10:30 New York (EDT)
        $this->assertTrue($this->hours->acceptsWalkIns($this->location, CarbonImmutable::parse('2026-10-05 14:30', 'UTC')));

        $this->location->update(['timezone' => 'America/Los_Angeles']); // 07:30 in LA
        $this->assertFalse($this->hours->acceptsWalkIns($this->location->fresh(), CarbonImmutable::parse('2026-10-05 14:30', 'UTC')));
    }

    public function test_check_in_after_last_walk_in_cutoff_is_refused_with_next_opening(): void
    {
        $at = $this->ny('2026-10-05 16:50'); // 10 min before close, cutoff 15

        $this->assertTrue($this->hours->isOpen($this->location, $at));
        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $at));
        $this->assertSame('2026-10-06 09:00', $this->hours->nextOpening($this->location, $at)->format('Y-m-d H:i'));
    }

    public function test_holiday_closure_and_special_hours(): void
    {
        Closure::create(['location_id' => $this->location->id, 'date' => '2026-10-12', 'reason' => 'Holiday']);
        Closure::create(['location_id' => $this->location->id, 'date' => '2026-10-13', 'opens_at' => '12:00', 'closes_at' => '15:00']);

        $this->assertSame([], $this->hours->windows($this->location, $this->ny('2026-10-12')));
        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-12 10:00')));

        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-13 10:00')));
        $this->assertTrue($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-13 13:00')));
        $this->assertSame('2026-10-13 12:00', $this->hours->nextOpening($this->location, $this->ny('2026-10-12 10:00'))->format('Y-m-d H:i'));
    }

    public function test_department_hours_are_narrower_and_default_to_location_hours(): void
    {
        $loans = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'L']);
        $general = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'G']);
        // Loans: Mondays 10:00–20:00 only (clipped to location's 17:00)
        OpeningHour::create(['location_id' => $this->location->id, 'department_id' => $loans->id, 'weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '20:00']);

        $windows = $this->hours->windows($this->location, $this->ny('2026-10-05'), $loans);
        $this->assertCount(1, $windows);
        $this->assertSame(['10:00', '17:00'], [$windows[0][0]->format('H:i'), $windows[0][1]->format('H:i')]);

        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-05 09:30'), $loans));
        $this->assertTrue($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-05 09:30'), $general));
        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-06 11:00'), $loans), 'Loans closed Tuesdays');
    }

    public function test_split_shifts_and_inactive_location(): void
    {
        OpeningHour::create(['location_id' => $this->location->id, 'weekday' => 6, 'opens_at' => '09:00', 'closes_at' => '12:00']);
        OpeningHour::create(['location_id' => $this->location->id, 'weekday' => 6, 'opens_at' => '13:00', 'closes_at' => '16:00']);

        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-10 12:30')));
        $this->assertSame('13:00', $this->hours->nextOpening($this->location, $this->ny('2026-10-10 12:30'))->format('H:i'));

        $this->location->update(['is_active' => false]);
        $this->assertFalse($this->hours->acceptsWalkIns($this->location, $this->ny('2026-10-05 10:00')));
    }
}
