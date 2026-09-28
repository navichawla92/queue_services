<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ordered routing rules per location (customer-routing spec).
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete(); // null = any
            $table->string('customer_type', 20)->nullable(); // walk_in | appointment | null = any
            $table->json('weekdays')->nullable();            // [0..6] | null = any day
            $table->time('starts_at')->nullable();           // local time window, null = all day
            $table->time('ends_at')->nullable();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->smallInteger('priority')->default(0);    // added to the ticket's priority
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['location_id', 'is_active', 'sort_order']);
        });

        // Rolling average service time per location & service (hourly refresh).
        Schema::create('service_time_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('avg_seconds');
            $table->unsignedInteger('sample_count');
            $table->dateTime('computed_at');

            $table->unique(['location_id', 'service_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('phone', 20)->nullable(); // E.164
            $table->string('email')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->dateTime('last_visit_at')->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
        });

        // Serializes all queue mutations of a location and versions its state.
        Schema::create('location_queue_states', function (Blueprint $table) {
            $table->foreignId('location_id')->primary()->constrained('locations')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->date('local_date');
            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['location_id', 'department_id', 'local_date']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();

            $table->string('number', 12);          // "A-012"
            $table->unsignedInteger('sequence');
            $table->date('local_date');
            $table->string('channel', 20);         // kiosk | qr | mobile | receptionist
            $table->string('customer_type', 20);   // walk_in | appointment
            $table->string('status', 20);
            $table->smallInteger('priority')->default(0);
            $table->string('public_token', 40)->unique();

            // Snapshot of who checked in (customer record may be anonymized later).
            $table->string('customer_name');
            $table->string('customer_phone', 20)->nullable();
            $table->boolean('sms_consent')->default(false);

            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('serving_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('desk_id')->nullable()->constrained('desks')->nullOnDelete();

            $table->dateTime('checked_in_at');
            $table->dateTime('queued_at');         // position time in the current queue
            $table->dateTime('first_called_at')->nullable();
            $table->dateTime('called_at')->nullable();
            $table->dateTime('service_started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('hold_started_at')->nullable();
            $table->unsignedInteger('total_hold_seconds')->default(0);
            $table->unsignedInteger('wait_seconds')->nullable();     // queue → first call, minus hold
            $table->unsignedInteger('service_seconds')->nullable();
            $table->unsignedSmallInteger('recall_count')->default(0);
            $table->unsignedSmallInteger('transfer_count')->default(0);
            $table->unsignedSmallInteger('estimated_wait_minutes')->nullable();
            $table->string('hold_reason')->nullable();
            $table->string('outcome')->nullable();
            $table->string('closed_reason', 30)->nullable();
            $table->timestamps();

            $table->index(['location_id', 'status', 'department_id']);
            $table->index(['tenant_id', 'local_date']);
            $table->index(['location_id', 'customer_phone', 'status']);
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('desk_id')->nullable()->constrained('desks')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->dateTime('created_at');

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        foreach (['notes', 'ticket_events', 'tickets', 'ticket_sequences', 'location_queue_states', 'customers', 'service_time_stats', 'routing_rules'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
