<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider credentials & sender. location_id null = company default;
        // a location row overrides the sender only.
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->string('provider', 20)->default('log'); // twilio | log
            $table->string('account_sid')->nullable();
            $table->text('auth_token')->nullable();         // encrypted cast
            $table->string('from_number', 20)->nullable();
            $table->string('messaging_service_sid')->nullable();
            $table->string('help_message', 320)->nullable();
            $table->time('quiet_start')->default('21:00');
            $table->time('quiet_end')->default('08:00');
            $table->unsignedSmallInteger('wait_update_threshold')->default(10);
            $table->unsignedSmallInteger('position_alert_at')->default(3);
            $table->timestamps();

            $table->unique(['tenant_id', 'location_id']);
        });

        // Per-event on/off; location rows override the company row.
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->string('event', 40);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['tenant_id', 'location_id', 'event']);
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('event', 40);
            $table->string('locale', 5)->default('en');
            $table->text('body');
            $table->timestamps();

            $table->unique(['tenant_id', 'event', 'locale']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('to', 20);
            $table->string('event', 40);
            $table->text('body');
            $table->string('status', 20);               // queued|sent|delivered|failed|undelivered|suppressed|skipped
            $table->string('status_reason')->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('provider_message_id', 64)->nullable()->unique();
            $table->unsignedSmallInteger('segments')->default(1);
            $table->decimal('price', 10, 5)->nullable();
            $table->string('price_unit', 3)->nullable();
            $table->string('error_code', 20)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['ticket_id', 'event']);
        });

        Schema::create('sms_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('phone', 20);
            $table->string('source', 20)->default('keyword');
            $table->dateTime('opted_out_at');

            $table->unique(['tenant_id', 'phone']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            // Last wait estimate texted to the customer ("significant change" check).
            $table->unsignedSmallInteger('notified_wait_minutes')->nullable()->after('estimated_wait_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('notified_wait_minutes'));
        foreach (['sms_opt_outs', 'sms_messages', 'sms_templates', 'notification_settings', 'sms_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
