<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Walk-ins are refused this many minutes before closing.
            $table->unsignedSmallInteger('walkin_cutoff_minutes')->default(15)->after('checkin_public_id');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('name');
            $table->string('prefix', 3);
            $table->string('color', 7)->default('#2563eb');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['location_id', 'prefix']);
            $table->index(['tenant_id', 'location_id']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('expected_minutes')->default(10);
            $table->boolean('allow_walk_in')->default(true);
            $table->boolean('allow_appointment')->default(false);
            $table->boolean('customer_selectable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Locations offering a service, with that location's default department.
        Schema::create('location_service', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments');
            $table->primary(['location_id', 'service_id']);
            $table->index('service_id');
        });

        Schema::create('desks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('label', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['location_id', 'label']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('display_name', 50);
            $table->foreignId('default_desk_id')->nullable()->constrained('desks')->nullOnDelete();
            $table->string('status', 20)->default('offline');
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('current_desk_id')->nullable()->constrained('desks')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'current_location_id', 'status']);
        });

        Schema::create('department_employee', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->primary(['department_id', 'employee_id']);
            $table->index('employee_id');
        });

        // Skills: services an employee can perform.
        Schema::create('employee_service', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->primary(['employee_id', 'service_id']);
            $table->index('service_id');
        });

        // Weekly hours. department_id null = location hours; set = narrower
        // department hours (a department with any rows uses only its rows).
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday (Carbon dayOfWeek)
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->index(['location_id', 'department_id', 'weekday']);
        });

        // Date-specific closures or special hours (holidays).
        Schema::create('closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->date('date');
            $table->time('opens_at')->nullable();  // both null = closed all day
            $table->time('closes_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closures');
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('employee_service');
        Schema::dropIfExists('department_employee');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('desks');
        Schema::dropIfExists('location_service');
        Schema::dropIfExists('services');
        Schema::dropIfExists('departments');
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('walkin_cutoff_minutes'));
    }
};
