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
        $this->renameGuildTagsTable(from: 'wcl_guild_tags', to: 'warcraft_logs_guild_tags');

        Schema::rename('wcl_zones', 'warcraft_logs_zones');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('warcraft_logs_zones', 'wcl_zones');

        $this->renameGuildTagsTable(from: 'warcraft_logs_guild_tags', to: 'wcl_guild_tags');
    }

    /**
     * Rename the guild tags table, recreating its phase foreign key so the
     * constraint and its backing index take the new table's name.
     */
    private function renameGuildTagsTable(string $from, string $to): void
    {
        Schema::table($from, function (Blueprint $table) use ($from): void {
            $table->dropForeign("{$from}_tbc_phase_id_foreign");
            $table->dropIndex("{$from}_tbc_phase_id_foreign");
        });

        Schema::rename($from, $to);

        Schema::table($to, function (Blueprint $table): void {
            $table->foreign('tbc_phase_id')->references('id')->on('phases')->nullOnDelete();
        });
    }
};
