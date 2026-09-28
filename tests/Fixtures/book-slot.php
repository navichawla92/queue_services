<?php

/*
 * Concurrency probe for tests/Feature/Scheduling/BookingConcurrencyTest.
 * Usage: php book-slot.php <tenantId> <locationId> <serviceId> <startUtc Y-m-d\TH:i> <phone> <startAtUnixFloat>
 * Prints JSON {appointment: id|null, error}.
 */

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Scheduling\AppointmentBooking;
use App\Domain\Scheduling\BookingRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $tenantId, $locationId, $serviceId, $start, $phone, $startAt] = $argv;

app(TenantContext::class)->set(Tenant::query()->findOrFail((int) $tenantId));
$location = Location::query()->findOrFail((int) $locationId);
$service = Service::query()->findOrFail((int) $serviceId);

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $a = app(AppointmentBooking::class)->book(new BookingRequest(
        $location, $service, CarbonImmutable::createFromFormat('Y-m-d\TH:i', $start, 'UTC'), 'Racer', $phone, null, false,
    ));
    echo json_encode(['appointment' => $a->id, 'error' => null]);
} catch (Throwable $e) {
    echo json_encode(['appointment' => null, 'error' => class_basename($e).': '.$e->getMessage()]);
}
