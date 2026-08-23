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
        Schema::table('loot_priorities', function (Blueprint $table) {
            $table->foreignId('playable_class_id')
                ->nullable()
                ->after('type')
                ->constrained('playable_classes')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loot_priorities', function (Blueprint $table) {
            $table->dropForeign(['playable_class_id']);
            $table->dropColumn('playable_class_id');
        });
    }
};
