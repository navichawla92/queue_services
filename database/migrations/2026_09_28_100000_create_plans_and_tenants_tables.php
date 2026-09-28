<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('features');
            $table->json('limits');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('public_id', 26)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('suspended_at')->nullable();

            // Branding
            $table->string('display_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#1d4ed8');
            $table->string('accent_color', 7)->default('#f59e0b');
            $table->text('public_text')->nullable();

            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants');
            $table->boolean('is_platform_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_platform_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['is_platform_admin', 'is_active']);
        });
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
