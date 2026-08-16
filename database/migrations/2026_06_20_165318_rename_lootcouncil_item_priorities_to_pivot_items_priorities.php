<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('lootcouncil_item_priorities', 'pivot_items_priorities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('pivot_items_priorities', 'lootcouncil_item_priorities');
    }
};
