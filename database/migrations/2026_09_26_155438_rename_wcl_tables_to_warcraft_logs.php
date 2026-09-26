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
        $this->renameGuildTagsTable(
            from: 'wcl_guild_tags',
            to: 'warcraft_logs_guild_tags',
            fromColumn: 'tbc_phase_id',
            toColumn: 'phase_id',
        );

        Schema::rename('wcl_zones', 'warcraft_logs_zones');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('warcraft_logs_zones', 'wcl_zones');

        $this->renameGuildTagsTable(
            from: 'warcraft_logs_guild_tags',
            to: 'wcl_guild_tags',
            fromColumn: 'phase_id',
            toColumn: 'tbc_phase_id',
        );
    }

    /**
     * Rename the guild tags table and its phase column, recreating the phase
     * foreign key so the constraint and its backing index take the new names.
     */
    private function renameGuildTagsTable(string $from, string $to, string $fromColumn, string $toColumn): void
    {
        Schema::table($from, function (Blueprint $table) use ($from, $fromColumn, $toColumn): void {
            $table->dropForeign("{$from}_{$fromColumn}_foreign");
            $table->dropIndex("{$from}_{$fromColumn}_foreign");
            $table->renameColumn($fromColumn, $toColumn);
        });

        Schema::rename($from, $to);

        Schema::table($to, function (Blueprint $table) use ($toColumn): void {
            $table->foreign($toColumn)->references('id')->on('phases')->nullOnDelete();
        });
    }
};
