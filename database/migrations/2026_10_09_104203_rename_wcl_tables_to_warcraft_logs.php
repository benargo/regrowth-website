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
        Schema::table('wcl_guild_tags', function (Blueprint $table): void {
            $table->dropForeign(['tbc_phase_id']);
            $table->dropColumn('tbc_phase_id');
        });

        Schema::rename('wcl_guild_tags', 'warcraft_logs_guild_tags');

        Schema::rename('wcl_zones', 'warcraft_logs_zones');
    }
};
