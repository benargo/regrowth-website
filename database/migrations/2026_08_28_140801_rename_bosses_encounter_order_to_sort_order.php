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
        Schema::table('bosses', function (Blueprint $table) {
            $table->renameColumn('encounter_order', 'sort_order');
        });

        Schema::table('bosses', function (Blueprint $table) {
            $table->integer('sort_order')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bosses', function (Blueprint $table) {
            $table->integer('sort_order')->nullable(false)->change();
        });

        Schema::table('bosses', function (Blueprint $table) {
            $table->renameColumn('sort_order', 'encounter_order');
        });
    }
};
