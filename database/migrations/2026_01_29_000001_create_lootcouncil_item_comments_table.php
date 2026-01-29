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
        Schema::create('lootcouncil_item_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('lootcouncil_items');
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->string('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lootcouncil_item_comments');
    }
};
