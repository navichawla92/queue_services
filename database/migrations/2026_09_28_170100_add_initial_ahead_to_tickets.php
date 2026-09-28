<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Waiting tickets ahead at check-in: position alerts are only for
            // customers who have moved up since.
            $table->unsignedSmallInteger('initial_ahead')->default(0)->after('estimated_wait_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('initial_ahead'));
    }
};
