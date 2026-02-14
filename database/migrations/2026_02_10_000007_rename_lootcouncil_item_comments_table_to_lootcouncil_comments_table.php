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
        Schema::table('lootcouncil_item_comments', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['deleted_by']);
        });

        Schema::rename('lootcouncil_item_comments', 'lootcouncil_comments');

        Schema::table('lootcouncil_comments', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('lootcouncil_items');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lootcouncil_comments', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['deleted_by']);
        });

        Schema::rename('lootcouncil_comments', 'lootcouncil_item_comments');

        Schema::table('lootcouncil_item_comments', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('lootcouncil_items');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }
};
