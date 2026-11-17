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
        Schema::create('membership_upgrades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_batch_id')
                ->nullable()
                ->constrained('membership_batches')
                ->nullOnDelete();
            $table->foreignId('upgrade_type_id')
                ->nullable()
                ->constrained('upgrade_types')
                ->nullOnDelete();
            $table->foreignId('previous_grade_id')
                ->nullable()
                ->constrained('membership_grades')
                ->restrictOnDelete();
            $table->foreignId('new_grade_id')
                ->constrained('membership_grades')
                ->restrictOnDelete();
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_upgrades');
    }
};
