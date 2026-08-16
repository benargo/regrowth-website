<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the single-raid foreign key now that pivot_items_raids is the source
     * of truth.
     *
     * Run this only after the updated ItemSeeder has populated the pivot in the
     * target environment — see issue #56.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign('lootcouncil_items_raid_id_foreign');
            $table->dropColumn('raid_id');
        });
    }

    /**
     * Reverse the migration.
     *
     * The restored column can only hold one raid per item, so a cross-raid item
     * collapses to its lowest raid id. This is lossy by design.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('raid_id')->nullable()->after('id');
            $table->foreign('raid_id', 'lootcouncil_items_raid_id_foreign')->references('id')->on('raids');
        });

        DB::statement('
            UPDATE items
            SET raid_id = (
                SELECT MIN(raid_id) FROM pivot_items_raids WHERE item_id = items.id
            )
        ');
    }
};
