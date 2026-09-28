<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Scheduling\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\BuildsSchedule;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * appointment-scheduling "Concurrent booking of last slot": two customers
 * submit the only remaining slot at the same instant from separate
 * processes; exactly one booking succeeds.
 */
class BookingConcurrencyTest extends TestCase
{
    use BuildsQueue, BuildsSchedule, DatabaseTruncation, InteractsWithTenants;

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_last_slot_is_booked_exactly_once(): void
    {
        [$a] = $this->twoTenants();
        $this->buildQueue($a);
        $this->buildSchedule();
        // One employee, one 30-minute slot tomorrow.
        $this->scheduledEmployee('Maria', '10:00', '10:30');
        $start = CarbonImmutable::now('UTC')->addDay()->format('Y-m-d').'T10:00';

        $startAt = microtime(true) + 3.0;
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.mysql.database'),
            'BROADCAST_CONNECTION' => 'null', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array',
        ]);

        $procs = [];
        $pipes = [];
        foreach (['+12025550101', '+12025550102'] as $i => $phone) {
            $cmd = [PHP_BINARY, base_path('tests/Fixtures/book-slot.php'), (string) $a->id, (string) $this->location->id, (string) $this->appt->id, $start, $phone, (string) $startAt];
            $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $p, base_path(), $env);
            $pipes[$i] = $p;
        }

        $results = [];
        foreach ($pipes as $i => $p) {
            $out = stream_get_contents($p[1]);
            $err = stream_get_contents($p[2]);
            proc_close($procs[$i]);
            $decoded = json_decode((string) $out, true);
            $this->assertIsArray($decoded, "Probe failed: {$out} {$err}");
            $results[] = $decoded;
        }

        $won = array_values(array_filter(array_column($results, 'appointment')));
        $this->assertCount(1, $won, 'Exactly one booking must succeed: '.json_encode($results));
        $this->assertStringContainsString('SlotUnavailableException', (string) collect($results)->firstWhere('appointment', null)['error']);
        $this->assertSame(1, Appointment::withoutTenantScope()->count());
    }
}
