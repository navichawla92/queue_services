<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nightly per-day rollup of tickets (design Decision 10). One row per
        // local date × location × department × service × serving employee
        // (0 = nobody) × customer type. Rebuilt idempotently per day.
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->date('local_date');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('employee_id')->default(0);
            $table->string('customer_type', 20);
            $table->unsignedInteger('tickets')->default(0);        // check-ins
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->unsignedInteger('cancelled')->default(0);      // left the queue
            $table->unsignedInteger('closed_unserved')->default(0);
            $table->unsignedBigInteger('wait_sum')->default(0);    // seconds, tickets that were called
            $table->unsignedInteger('wait_count')->default(0);
            $table->unsignedBigInteger('service_sum')->default(0); // seconds, completed tickets
            $table->unsignedInteger('service_count')->default(0);
            $table->json('checkins_by_hour');                      // 24 ints, location local hour
            $table->timestamps();

            $table->index(['tenant_id', 'local_date']);
            $table->index(['location_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
