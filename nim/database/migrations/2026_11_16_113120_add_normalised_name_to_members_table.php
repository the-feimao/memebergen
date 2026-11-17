<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('normalised_name')->nullable()->after('full_name');
            $table->index('normalised_name');
        });

        DB::table('members')
            ->select(['id', 'full_name'])
            ->orderBy('id')
            ->chunkById(500, static function (Collection $members): void {
                foreach ($members as $member) {
                    DB::table('members')
                        ->where('id', $member->id)
                        ->update([
                            'normalised_name' => Str::of((string) $member->full_name)->squish()->lower()->toString(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['normalised_name']);
            $table->dropColumn('normalised_name');
        });
    }
};
