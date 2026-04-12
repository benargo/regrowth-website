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
        Schema::table('characters', function (Blueprint $table) {
            $table->json('playable_class')->nullable()->after('rank_id');
            $table->dropColumn('playable_class_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('playable_class_id')->nullable()->after('rank_id');
            $table->dropColumn('playable_class');
        });
    }
};
