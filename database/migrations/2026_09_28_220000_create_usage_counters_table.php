<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Metered usage per tenant per billing period (YYYY-MM).
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('metric', 40);     // sms_segments | tickets | appointments
            $table->char('period', 7);        // 2026-10
            $table->unsignedBigInteger('value')->default(0);
            $table->boolean('warned_80')->default(false);
            $table->boolean('warned_100')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
