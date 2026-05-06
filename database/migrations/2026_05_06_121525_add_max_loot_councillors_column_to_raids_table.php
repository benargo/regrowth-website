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
        Schema::table('raids', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_players')->nullable()->change();
            $table->unsignedTinyInteger('max_loot_councillors')->nullable()->after('max_players');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raids', function (Blueprint $table) {
            $table->dropColumn('max_loot_councillors');
            $table->integer('max_players')->nullable(false)->change();
        });
    }
};
