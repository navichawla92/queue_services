<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A paired kiosk or lobby display.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('type', 20); // display | kiosk
            $table->string('name');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->json('config')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'location_id', 'type']);
        });

        // Short-lived pairing requests from unpaired browsers. No tenant until
        // an admin claims the code.
        Schema::create('device_pairings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('type', 20);
            $table->string('claim_hash', 64);
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->text('token_encrypted')->nullable(); // one-time hand-off, wiped once collected
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairings');
        Schema::dropIfExists('devices');
    }
};
