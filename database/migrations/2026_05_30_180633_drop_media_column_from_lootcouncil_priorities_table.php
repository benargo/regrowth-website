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
        Schema::table('lootcouncil_priorities', function (Blueprint $table) {
            if (Schema::hasColumn('lootcouncil_priorities', 'media')) {
                $table->dropColumn('media');
            }

            if (! Schema::hasIndex('lootcouncil_priorities', 'lootcouncil_priorities_title_unique')) {
                $table->unique('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lootcouncil_priorities', function (Blueprint $table) {
            if (! Schema::hasColumn('lootcouncil_priorities', 'media')) {
                $table->json('media')->nullable();
            }

            if (Schema::hasIndex('lootcouncil_priorities', 'lootcouncil_priorities_title_unique')) {
                $table->dropUnique(['title']);
            }
        });
    }
};
