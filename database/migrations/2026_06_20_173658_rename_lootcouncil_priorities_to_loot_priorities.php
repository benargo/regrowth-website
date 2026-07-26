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
        Schema::rename('lootcouncil_priorities', 'loot_priorities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('loot_priorities', 'lootcouncil_priorities');
    }
};
