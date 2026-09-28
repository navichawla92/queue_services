<?php

/*
 * Concurrency probe for tests/Feature/Queue/CallNextConcurrencyTest.
 * Usage: php call-next.php <tenantId> <locationId> <employeeId> <startAtUnixFloat>
 * Boots the app against the test database, waits until the shared start
 * instant, calls "call next" once and prints JSON {ticket: id|null, error}.
 */

use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Actor;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $tenantId, $locationId, $employeeId, $startAt] = $argv;

$tenant = Tenant::query()->findOrFail((int) $tenantId);
app(TenantContext::class)->set($tenant);
$location = Location::query()->findOrFail((int) $locationId);
$employee = Employee::query()->findOrFail((int) $employeeId);

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $ticket = app(TicketStateMachine::class)->callNext($employee, $location, Actor::employee($employee));
    echo json_encode(['ticket' => $ticket?->id, 'error' => null]);
} catch (Throwable $e) {
    echo json_encode(['ticket' => null, 'error' => $e->getMessage()]);
}
