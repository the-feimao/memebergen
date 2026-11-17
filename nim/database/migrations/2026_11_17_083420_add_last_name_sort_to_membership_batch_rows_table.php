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
        Schema::table('membership_batch_rows', function (Blueprint $table): void {
            $table->string('last_name_sort')->nullable()->after('full_name');
            $table->index(['membership_batch_id', 'last_name_sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_batch_rows', function (Blueprint $table): void {
            $table->dropIndex(['membership_batch_id', 'last_name_sort']);
            $table->dropColumn('last_name_sort');
        });
    }
};
