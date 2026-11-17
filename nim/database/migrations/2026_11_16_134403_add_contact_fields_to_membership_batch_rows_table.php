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
        Schema::table('membership_batch_rows', function (Blueprint $table) {
            $table->string('email')->nullable()->after('full_name');
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('state')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_batch_rows', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'state']);
        });
    }
};
