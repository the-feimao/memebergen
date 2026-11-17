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
        Schema::table('membership_batches', function (Blueprint $table): void {
            $table->dropUnique(['batch_type', 'file_sha256']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_batches', function (Blueprint $table): void {
            $table->unique(['batch_type', 'file_sha256']);
        });
    }
};
