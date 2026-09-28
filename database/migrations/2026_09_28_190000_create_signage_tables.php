<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('type', 20); // announcement | image | video | qr | service_info | rich_text
            $table->string('title');
            $table->text('body')->nullable();          // text / markdown / QR caption
            $table->string('url', 2048)->nullable();   // QR target
            $table->string('media_path')->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable(); // null for video (plays to end)
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->foreignId('signage_item_id')->constrained('signage_items')->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->index(['playlist_id', 'position']);
        });

        // Where and when a playlist plays. Target: company (both null), a
        // location, or one display. The most specific active schedule wins;
        // is_default schedules apply when nothing else matches.
        Schema::create('playlist_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('weekdays')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
        });

        Schema::create('ticker_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete(); // null = all
            $table->string('body', 280);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ticker_messages', 'playlist_schedules', 'playlist_items', 'playlists', 'signage_items'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
