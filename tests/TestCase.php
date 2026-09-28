<?php

namespace Tests;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if (isset($this->app)) {
            $this->app->make(TenantContext::class)->clear();
        }

        parent::tearDown();
    }
}
