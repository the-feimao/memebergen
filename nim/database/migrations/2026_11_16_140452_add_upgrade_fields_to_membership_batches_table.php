<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('membership_batches', function (Blueprint $table) {
            $table->foreignId('upgrade_type_id')
                ->nullable()
                ->after('batch_type')
                ->constrained('upgrade_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upgrade_type_id');
        });
    }
};
