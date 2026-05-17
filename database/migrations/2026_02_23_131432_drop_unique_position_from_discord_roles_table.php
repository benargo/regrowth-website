<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discord_roles', function (Blueprint $table) {
            if (Arr::first(Schema::getIndexes('discord_roles'), fn ($index) => $index['name'] === 'discord_roles_position_unique')) {
                $table->dropUnique('discord_roles_position_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Do not re-add the unique constraint on position, as it is not actually unique and this would
     * cause errors when trying to add new roles with duplicate positions.
     */
    public function down(): void
    {
        //
    }
};
