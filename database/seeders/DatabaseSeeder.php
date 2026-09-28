<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(PlansSeeder::class);

        if (app()->environment('local')) {
            $this->call(LocalDemoSeeder::class);
        }
    }
}
