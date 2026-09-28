<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('feedback_enabled')->default(true);
        });

        // One request per visit (ticket); the token is the single-use link.
        Schema::create('feedback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations');
            $table->string('token', 40)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        // Denormalized attribution so reports survive later setup changes.
        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('feedback_request_id')->unique()->constrained('feedback_requests')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedTinyInteger('rating');   // 1–5
            $table->json('answers')->nullable();     // extra questions: [{question, rating}]
            $table->text('comment')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'submitted_at']);
            $table->index(['employee_id', 'submitted_at']);
            $table->index(['location_id', 'submitted_at']);
        });

        // Laravel database notifications (in-app low-score alerts).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('feedback_responses');
        Schema::dropIfExists('feedback_requests');
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('feedback_enabled'));
    }
};
