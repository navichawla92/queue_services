<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('booking_enabled')->default(true);
            $table->boolean('booking_choose_employee')->default(false);
            $table->unsignedInteger('booking_lead_minutes')->default(120);   // earliest bookable = now + lead
            $table->unsignedSmallInteger('booking_horizon_days')->default(60);
            $table->unsignedSmallInteger('booking_buffer_minutes')->default(5); // added to service duration
            $table->unsignedSmallInteger('booking_cutoff_minutes')->default(60); // no online changes within
            $table->unsignedSmallInteger('appointment_capacity_per_hour')->nullable(); // null = no cap
            $table->unsignedSmallInteger('appointment_grace_minutes')->default(15);   // auto no-show after
        });

        // Recurring weekly working hours per employee per location (local time).
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['employee_id', 'location_id', 'weekday']);
        });

        Schema::create('time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'starts_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_email')->nullable();
            $table->boolean('sms_consent')->default(false);
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');
            $table->string('status', 20);
            $table->string('source', 20);  // online | staff
            $table->string('confirmation_code', 8);
            $table->string('manage_token', 40)->unique();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedSmallInteger('reschedule_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'confirmation_code']);
            $table->index(['location_id', 'starts_at']);
            $table->index(['employee_id', 'starts_at']);
            $table->index(['tenant_id', 'status', 'starts_at']);
        });

        // One row per (appointment, reminder offset) sent: makes reminders idempotent.
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->unsignedInteger('offset_minutes');
            $table->dateTime('sent_at');

            $table->primary(['appointment_id', 'offset_minutes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('time_off');
        Schema::dropIfExists('employee_schedules');
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn([
            'booking_enabled', 'booking_choose_employee', 'booking_lead_minutes', 'booking_horizon_days',
            'booking_buffer_minutes', 'booking_cutoff_minutes', 'appointment_capacity_per_hour', 'appointment_grace_minutes',
        ]));
    }
};
