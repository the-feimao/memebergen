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
            $table->string('supplied_membership_number')->nullable()->after('allocated_membership_number');
            $table->string('supplied_full_name')->nullable()->after('supplied_membership_number');
            $table->string('intended_grade_value')->nullable()->after('supplied_full_name');
            $table->foreignId('previous_grade_id')
                ->nullable()
                ->after('membership_grade_id')
                ->constrained('membership_grades')
                ->nullOnDelete();
            $table->foreignId('intended_grade_id')
                ->nullable()
                ->after('previous_grade_id')
                ->constrained('membership_grades')
                ->nullOnDelete();
            $table->foreignId('upgrade_type_id')
                ->nullable()
                ->after('intended_grade_id')
                ->constrained('upgrade_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_batch_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upgrade_type_id');
            $table->dropConstrainedForeignId('intended_grade_id');
            $table->dropConstrainedForeignId('previous_grade_id');
            $table->dropColumn(['supplied_membership_number', 'supplied_full_name', 'intended_grade_value']);
        });
    }
};
