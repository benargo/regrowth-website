<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the item↔raid pivot and seed it from the existing single-FK column.
     *
     * `items.raid_id` is deliberately left in place — it is dropped by a separate
     * migration once the updated ItemSeeder has run in each environment.
     */
    public function up(): void
    {
        Schema::create('pivot_items_raids', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('raid_id')->constrained('raids')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['item_id', 'raid_id']);
        });

        DB::statement('
            INSERT INTO pivot_items_raids (item_id, raid_id, created_at, updated_at)
            SELECT id, raid_id, NOW(), NOW() FROM items WHERE raid_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_items_raids');
    }
};
