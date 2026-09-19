<?php

use App\Models\GameVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['bosses', 'phases', 'raids', 'wcl_guild_tags', 'wcl_zones'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignIdFor(GameVersion::class)->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['game_version_id']);
                $blueprint->dropColumn('game_version_id');
            });
        }
    }
};
