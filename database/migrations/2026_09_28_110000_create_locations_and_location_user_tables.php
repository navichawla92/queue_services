<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('timezone', 64)->nullable(); // null = tenant default
            $table->string('phone', 32)->nullable();
            $table->string('checkin_public_id', 26)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Role applies to every location of the tenant (vs. an assigned set).
            $table->boolean('all_locations')->default(false)->after('is_active');
        });

        Schema::create('location_user', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['location_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('all_locations'));
        Schema::dropIfExists('locations');
    }
};
