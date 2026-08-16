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
        Schema::rename('lootcouncil_comments', 'comments');
        Schema::rename('lootcouncil_comments_reactions', 'pivot_comments_reactions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('pivot_comments_reactions', 'lootcouncil_comments_reactions');
        Schema::rename('comments', 'lootcouncil_comments');
    }
};
