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
        Schema::table('members', function (Blueprint $table): void {
            $table->date('date_of_birth')->nullable()->after('state');
            $table->string('address')->nullable()->after('date_of_birth');
        });

        Schema::table('membership_batch_rows', function (Blueprint $table): void {
            $table->date('date_of_birth')->nullable()->after('state');
            $table->string('address')->nullable()->after('date_of_birth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn(['date_of_birth', 'address']);
        });

        Schema::table('membership_batch_rows', function (Blueprint $table): void {
            $table->dropColumn(['date_of_birth', 'address']);
        });
    }
};
