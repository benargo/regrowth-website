<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateCount = DB::table('pivot_items_priorities')
            ->groupBy('item_id', 'priority_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateCount > 0) {
            throw new RuntimeException(
                "Cannot add unique index: {$duplicateCount} duplicate (item_id, priority_id) combination(s) exist in pivot_items_priorities. Remove duplicates manually before running this migration."
            );
        }

        Schema::table('pivot_items_priorities', function (Blueprint $table): void {
            $table->unique(['item_id', 'priority_id'], 'pivot_items_priorities_item_id_priority_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pivot_items_priorities', function (Blueprint $table): void {
            $table->dropUnique('pivot_items_priorities_item_id_priority_id_unique');
        });
    }
};
