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
        Schema::create('playable_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->after('name')->nullable();
            $table->foreignId('playable_class_id')->after('rank_id')->nullable()->constrained('playable_classes')->nullOnDelete();
            $table->dropColumn('playable_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('level');
            $table->dropForeign(['playable_class_id']);
            $table->dropColumn('playable_class_id');
            $table->json('playable_class')->after('rank_id')->nullable();
        });

        Schema::dropIfExists('playable_classes');
    }
};
