<?php

namespace Tests\Feature\Queue;

use App\Domain\Organization\Models\Employee;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\Models\TicketEvent;
use App\Domain\Queue\TicketStatus;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Two real PHP processes press "call next" at the same instant with one
 * waiting ticket: exactly one must get it (staff-queue "Call next customer").
 * Uses committed data (truncation) because the processes use their own
 * database connections.
 */
class CallNextConcurrencyTest extends TestCase
{
    use BuildsQueue, DatabaseTruncation, InteractsWithTenants;

    /** Truncation runs before each test; also clean up so later transactional tests start empty. */
    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_concurrent_call_next_never_double_claims(): void
    {
        [$a] = $this->twoTenants();
        $this->buildQueue($a);
        $maria = $this->onShiftEmployee('Maria');
        $sam = $this->onShiftEmployee('Sam');
        $ticket = $this->issue('Only customer');

        for ($round = 0; $round < 3; $round++) {
            $results = $this->race([$maria->id, $sam->id]);

            $claimed = array_values(array_filter(array_column($results, 'ticket')));
            $this->assertCount(1, $claimed, 'Exactly one process must claim the ticket: '.json_encode($results));
            $this->assertSame($ticket->id, $claimed[0]);
            $this->assertSame(1, TicketEvent::withoutTenantScope()->where('ticket_id', $ticket->id)->where('type', 'called')->count());

            // Reset for another round.
            Ticket::withoutTenantScope()->whereKey($ticket->id)->update(['status' => TicketStatus::Waiting->value, 'serving_employee_id' => null]);
            TicketEvent::withoutTenantScope()->where('type', 'called')->delete();
            Employee::withoutTenantScope()->whereIn('id', [$maria->id, $sam->id])->update(['status' => 'available']);
        }
    }

    /**
     * @param  list<int>  $employeeIds
     * @return list<array{ticket: int|null, error: string|null}>
     */
    private function race(array $employeeIds): array
    {
        $startAt = microtime(true) + 3.0; // both processes booted before this instant
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'BROADCAST_CONNECTION' => 'null',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
        ]);

        $procs = [];
        $outputs = [];
        foreach ($employeeIds as $employeeId) {
            $cmd = [PHP_BINARY, base_path('tests/Fixtures/call-next.php'), (string) $this->location->tenant_id, (string) $this->location->id, (string) $employeeId, (string) $startAt];
            $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $outputs[] = $pipes;
        }

        $results = [];
        foreach ($outputs as $i => $pipes) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            proc_close($procs[$i]);
            $decoded = json_decode((string) $out, true);
            $this->assertIsArray($decoded, "Probe failed: {$out} {$err}");
            $results[] = $decoded;
        }

        return $results;
    }
}
