<?php

use App\Models\Character;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table of guild membership periods for each character.
     */
    public function up(): void
    {
        Schema::create('character_guild_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Character::class)->constrained()->cascadeOnDelete();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->index(['character_id', 'joined_on']);
        });
    }

    /**
     * Drop the guild membership periods table.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_guild_memberships');
    }
};
