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
        Schema::create('membership_batch_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_batch_id')
                ->constrained('membership_batches')
                ->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->text('full_name')->nullable();
            $table->text('grade_value')->nullable();
            $table->string('normalised_name')->nullable();
            $table->foreignId('membership_grade_id')
                ->nullable()
                ->constrained('membership_grades')
                ->restrictOnDelete();
            $table->string('status', 40);
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('allocated_membership_number')->nullable();
            $table->timestamps();

            $table->unique(['membership_batch_id', 'row_number']);
            $table->index(['membership_batch_id', 'status']);
            $table->index(['membership_batch_id', 'normalised_name', 'row_number'], 'mbr_batch_norm_name_row_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_batch_rows');
    }
};
