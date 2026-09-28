<?php

namespace Database\Seeders;

use App\Domain\Billing\PlanCatalog;
use App\Domain\Tenancy\Models\Plan;
use Illuminate\Database\Seeder;

/** Idempotent: creates/updates the catalog plans. */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlanCatalog::seeded() as $code => $plan) {
            Plan::query()->updateOrCreate(['code' => $code], $plan);
        }
    }
}
