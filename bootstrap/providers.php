<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\NotificationsServiceProvider;
use App\Providers\OrganizationServiceProvider;
use App\Providers\QueueServiceProvider;
use App\Providers\SchedulingServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    AccessServiceProvider::class,
    OrganizationServiceProvider::class,
    QueueServiceProvider::class,
    NotificationsServiceProvider::class,
    SchedulingServiceProvider::class,
    FortifyServiceProvider::class,
];
